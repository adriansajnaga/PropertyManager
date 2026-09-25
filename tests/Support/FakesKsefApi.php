<?php

namespace Tests\Support;

use App\Enums\KsefEnvironment;
use App\Models\KsefSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\File\X509;
use phpseclib3\Math\BigInteger;

/**
 * Udaje API KSeF razem z jego kryptografią: testy mogą odszyfrować to, co
 * aplikacja faktycznie wysłała, zamiast wierzyć w kolejność wywołań.
 */
trait FakesKsefApi
{
    private PrivateKey $ksefKey;

    private string $ksefCertificate;

    protected function configureKsef(string $nip = '8792451081'): void
    {
        [$this->ksefKey, $this->ksefCertificate] = $this->generateKsefCertificate();

        KsefSetting::create([
            'environment' => KsefEnvironment::Test,
            'nip' => $nip,
            'token' => 'TOKEN',
        ]);

        // Uwierzytelnianie ma własne testy — tutaj wystarczy gotowy token dostępowy.
        Cache::put('ksef.access-token.test.'.$nip, 'DOSTEPOWY', 600);
    }

    /**
     * @return array{0: PrivateKey, 1: string}
     */
    private function generateKsefCertificate(): array
    {
        $key = RSA::createKey(2048);

        $subject = new X509;
        $subject->setPublicKey($key->getPublicKey());
        $subject->setDN(['CN' => 'Ministerstwo Finansow']);

        $issuer = new X509;
        $issuer->setPrivateKey($key);
        $issuer->setDN($subject->getDN());

        $authority = new X509;
        $authority->setSerialNumber(new BigInteger(1), 10);
        $authority->setStartDate('-1 day');
        $authority->setEndDate('+1 year');

        $pem = $authority->saveX509($authority->sign($issuer, $subject));

        return [$key, preg_replace('/-----[^-]+-----|\s+/', '', $pem)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function ksefPublicKeysResponse(): array
    {
        return [
            [
                'certificate' => $this->ksefCertificate,
                'certificateId' => 'cert-token',
                'publicKeyId' => 'klucz-token',
                'usage' => ['KsefTokenEncryption'],
            ],
            [
                'certificate' => $this->ksefCertificate,
                'certificateId' => 'cert-sym',
                'publicKeyId' => 'klucz-sym',
                'usage' => ['SymmetricKeyEncryption'],
            ],
        ];
    }

    /** Odszyfrowuje klucz symetryczny tak, jak zrobiłby to KSeF. */
    protected function decryptSymmetricKey(string $encryptedBase64): string
    {
        return $this->ksefKey
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256')
            ->decrypt(base64_decode($encryptedBase64));
    }

    /** Treść faktury wydobyta z tego, co aplikacja wysłała do sesji. */
    protected function decryptInvoice(array $sessionPayload, array $invoicePayload): string
    {
        return openssl_decrypt(
            base64_decode($invoicePayload['encryptedInvoiceContent']),
            'aes-256-cbc',
            $this->decryptSymmetricKey($sessionPayload['encryption']['encryptedSymmetricKey']),
            OPENSSL_RAW_DATA,
            base64_decode($sessionPayload['encryption']['initializationVector']),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function fakeKsefSending(array $overrides = []): void
    {
        Http::fake(array_merge([
            '*/security/public-key-certificates' => Http::response($this->ksefPublicKeysResponse()),
            '*/sessions/online' => Http::response([
                'referenceNumber' => 'SESJA-1',
                'validUntil' => now()->addHours(12)->toIso8601String(),
            ]),
            '*/sessions/online/SESJA-1/invoices' => Http::response(['referenceNumber' => 'FAKTURA-1'], 202),
            '*/sessions/SESJA-1/invoices/FAKTURA-1' => Http::response([
                'referenceNumber' => 'FAKTURA-1',
                'ksefNumber' => '8792451081-20260926-9132F5C00005-ED',
                'status' => ['code' => 200, 'description' => 'Faktura przyjęta'],
            ]),
            '*/sessions/online/SESJA-1/close' => Http::response([], 202),
            '*/invoices/query/metadata*' => Http::response(['invoices' => [], 'hasMore' => false]),
        ], $overrides));
    }
}
