<?php

namespace App\Services\Ksef;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\KsefSetting;
use Throwable;

/**
 * Wysyłka faktury do KSeF sesją interaktywną.
 *
 * Treść XML szyfrujemy AES-256-CBC (PKCS#7), a klucz symetryczny — RSA-OAEP SHA-256
 * kluczem publicznym Ministerstwa Finansów, zgodnie z wymaganiami sesji.
 */
class KsefInvoiceSender
{
    private const STATUS_ATTEMPTS = 12;

    private const STATUS_DELAY_MS = 900;

    public function __construct(
        private readonly KsefClient $client,
        private readonly Fa3InvoiceBuilder $builder,
        private readonly KsefInvoiceImporter $importer,
    ) {}

    public function send(Invoice $invoice): Invoice
    {
        if ($invoice->isInKsef()) {
            throw new KsefException('Ta faktura jest już w KSeF pod numerem '.$invoice->ksef_number.'.');
        }

        $xml = $this->builder->build($invoice->load('lines', 'tenant'));

        $key = random_bytes(32);
        $iv = random_bytes(16);

        $encrypted = openssl_encrypt($xml, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new KsefException('Nie udało się zaszyfrować faktury przed wysyłką.');
        }

        [$encryptedKey, $publicKeyId] = $this->client->encryptWithPublicKey($key, 'SymmetricKeyEncryption');

        $session = $this->client->openOnlineSession($encryptedKey, base64_encode($iv), $publicKeyId);

        try {
            $reference = $this->client->sendInvoice($session, [
                'invoiceHash' => base64_encode(hash('sha256', $xml, true)),
                'invoiceSize' => strlen($xml),
                'encryptedInvoiceHash' => base64_encode(hash('sha256', $encrypted, true)),
                'encryptedInvoiceSize' => strlen($encrypted),
                'encryptedInvoiceContent' => base64_encode($encrypted),
            ]);

            $status = $this->awaitAcceptance($session, $reference);
        } catch (KsefException $e) {
            $invoice->update([
                'status' => InvoiceStatus::Rejected,
                'ksef_error' => $e->getMessage(),
                'xml' => $xml,
            ]);

            $this->closeQuietly($session);

            throw $e;
        }

        $this->closeQuietly($session);

        $invoice->update([
            'status' => InvoiceStatus::Sent,
            'ksef_number' => $status['ksefNumber'] ?? null,
            'ksef_reference' => $reference,
            'ksef_environment' => KsefSetting::current()->environment,
            'ksef_sent_at' => now(),
            'ksef_error' => null,
            'xml' => $xml,
        ]);

        $invoice->refresh();

        // KSeF jest źródłem prawdy: zaraz po przyjęciu pobieramy stamtąd fakturę
        // i to jej treścią nadpisujemy zapis w bazie. Gdy dokument nie zdążył się
        // jeszcze pojawić, zostaje wysłany XML — potwierdzi go kolejny import.
        try {
            $this->importer->confirm($invoice);
        } catch (Throwable $e) {
            report($e);
        }

        return $invoice->refresh();
    }

    /**
     * Weryfikacja faktury jest asynchroniczna — czekamy na numer KSeF albo na powód odmowy.
     *
     * @return array<string, mixed>
     */
    private function awaitAcceptance(string $session, string $reference): array
    {
        for ($attempt = 1; $attempt <= self::STATUS_ATTEMPTS; $attempt++) {
            $status = $this->client->invoiceStatus($session, $reference);
            $code = (int) ($status['status']['code'] ?? 0);

            if (filled($status['ksefNumber'] ?? null)) {
                return $status;
            }

            if ($code >= 400) {
                throw new KsefException('KSeF odrzucił fakturę: '.($status['status']['description'] ?? 'kod '.$code));
            }

            usleep(self::STATUS_DELAY_MS * 1000);
        }

        throw new KsefException('KSeF nie potwierdził przyjęcia faktury w wyznaczonym czasie. Sprawdź jej status później.');
    }

    /** Zamknięcie sesji to sprzątanie — jego błąd nie może przesłonić wyniku wysyłki. */
    private function closeQuietly(string $session): void
    {
        try {
            $this->client->closeOnlineSession($session);
        } catch (KsefException) {
            // sesja i tak wygaśnie sama po 12 godzinach
        }
    }
}
