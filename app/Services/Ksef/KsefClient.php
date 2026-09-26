<?php

namespace App\Services\Ksef;

use App\Enums\KsefEnvironment;
use App\Models\KsefSetting;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;

/**
 * Klient API KSeF 2.0 — na razie w zakresie uwierzytelniania tokenem KSeF.
 *
 * Przebieg wynika z dokumentacji Ministerstwa Finansów (CIRFMF/ksef-api):
 *   1. POST /auth/challenge                → challenge + timestampMs
 *   2. szyfrowanie „token|timestampMs"     → RSA-OAEP SHA-256 kluczem publicznym KSeF
 *   3. POST /auth/ksef-token               → numer referencyjny + token operacyjny
 *   4. GET  /auth/{referenceNumber}        → status operacji (kod 200 = sukces)
 *   5. POST /auth/token/redeem             → accessToken używany w kolejnych wywołaniach
 */
class KsefClient
{
    /** Ile razy pytamy o wynik uwierzytelnienia, zanim uznamy je za nieudane. */
    private const STATUS_ATTEMPTS = 10;

    private const STATUS_DELAY_MS = 700;

    public function __construct(private readonly KsefSetting $settings) {}

    public static function forCurrentSettings(): self
    {
        return new self(KsefSetting::current());
    }

    /**
     * Token dostępowy do wywołań API. Trzymany w cache do czasu wygaśnięcia,
     * bo każde uwierzytelnienie to cztery wywołania i operacja kryptograficzna.
     */
    public function accessToken(): string
    {
        $key = $this->cacheKey();

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $token = $this->authenticate();

        $validUntil = isset($token['validUntil']) ? strtotime((string) $token['validUntil']) : false;
        $seconds = $validUntil ? max(60, $validUntil - time() - 60) : 600;

        Cache::put($key, $token['token'], $seconds);

        return $token['token'];
    }

    public function cacheKey(): string
    {
        return 'ksef.access-token.'
            .($this->settings->environment ?? KsefEnvironment::Test)->value
            .'.'.$this->settings->nip;
    }

    /**
     * Pełny przebieg uwierzytelnienia tokenem KSeF.
     *
     * @return array{token: string, validUntil?: string}
     */
    public function authenticate(): array
    {
        if (! $this->settings->isConfigured()) {
            throw new KsefException('Uzupełnij NIP i token KSeF w ustawieniach.');
        }

        $challenge = $this->json($this->request()->post('/auth/challenge'));

        [$encryptedToken, $publicKeyId] = $this->encryptToken(
            (string) $this->settings->token,
            (int) ($challenge['timestampMs'] ?? 0),
        );

        $init = $this->json($this->request()->post('/auth/ksef-token', array_filter([
            'challenge' => $challenge['challenge'] ?? null,
            'contextIdentifier' => ['type' => 'Nip', 'value' => $this->settings->nip],
            'encryptedToken' => $encryptedToken,
            'publicKeyId' => $publicKeyId,
        ])));

        $operationToken = $init['authenticationToken']['token'] ?? null;
        $reference = $init['referenceNumber'] ?? null;

        if (! $operationToken || ! $reference) {
            throw new KsefException('KSeF nie zwrócił tokena operacyjnego.');
        }

        $this->awaitAuthentication($reference, $operationToken);

        $tokens = $this->json($this->request($operationToken)->post('/auth/token/redeem'));

        if (! isset($tokens['accessToken']['token'])) {
            throw new KsefException('KSeF nie zwrócił tokena dostępowego.');
        }

        return $tokens['accessToken'];
    }

    /**
     * Bieżąca sesja uwierzytelnienia — używana do sprawdzenia, czy konfiguracja działa.
     * Listę daje GET /auth/sessions; ścieżka /auth/sessions/current obsługuje wyłącznie
     * DELETE (unieważnienie), więc odpytywanie jej kończyło się odpowiedzią 405.
     */
    public function currentSession(): array
    {
        $response = $this->json(
            $this->request($this->accessToken())->get('/auth/sessions', ['pageSize' => 20]),
        );

        $sessions = $response['items'] ?? [];

        foreach ($sessions as $session) {
            if ($session['isCurrent'] ?? false) {
                return $session;
            }
        }

        return $sessions[0] ?? [];
    }

    /** Otwarcie sesji interaktywnej; zwraca jej numer referencyjny. */
    public function openOnlineSession(string $encryptedKey, string $initializationVector, ?string $publicKeyId): string
    {
        $response = $this->json($this->request($this->accessToken())->post('/sessions/online', [
            'formCode' => ['systemCode' => 'FA (3)', 'schemaVersion' => '1-0E', 'value' => 'FA'],
            'encryption' => array_filter([
                'encryptedSymmetricKey' => $encryptedKey,
                'initializationVector' => $initializationVector,
                'publicKeyId' => $publicKeyId,
            ]),
        ]));

        if (! isset($response['referenceNumber'])) {
            throw new KsefException('KSeF nie otworzył sesji wysyłkowej.');
        }

        return $response['referenceNumber'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return string numer referencyjny faktury w sesji
     */
    public function sendInvoice(string $session, array $payload): string
    {
        $response = $this->json(
            $this->request($this->accessToken())->post('/sessions/online/'.$session.'/invoices', $payload),
        );

        if (! isset($response['referenceNumber'])) {
            throw new KsefException('KSeF nie przyjął faktury do sesji.');
        }

        return $response['referenceNumber'];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceStatus(string $session, string $invoiceReference): array
    {
        return $this->json($this->request($this->accessToken())
            ->get('/sessions/'.$session.'/invoices/'.$invoiceReference));
    }

    public function closeOnlineSession(string $session): void
    {
        $this->json($this->request($this->accessToken())->post('/sessions/online/'.$session.'/close'));
    }

    /**
     * Szyfruje dane kluczem publicznym KSeF o wskazanym przeznaczeniu.
     *
     * @return array{0: string, 1: string|null} szyfrogram w Base64 oraz identyfikator klucza
     */
    public function encryptWithPublicKey(string $data, string $usage): array
    {
        $certificate = $this->publicKeyCertificate($usage);

        $x509 = new X509;
        $x509->loadX509((string) $certificate['certificate']);
        $publicKey = $x509->getPublicKey();

        if (! $publicKey instanceof RSA) {
            throw new KsefException('Certyfikat KSeF nie zawiera klucza RSA.');
        }

        $rsa = $publicKey
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256');

        return [base64_encode($rsa->encrypt($data)), $certificate['publicKeyId'] ?? null];
    }

    /**
     * Metadane faktur z KSeF. `subjectType` = Subject1 oznacza dokumenty, w których
     * jesteśmy sprzedawcą, czyli faktury wystawione naszym najemcom.
     *
     * @return array{invoices: array<int, array<string, mixed>>, hasMore: bool}
     */
    public function queryInvoiceMetadata(
        CarbonInterface $from,
        CarbonInterface $to,
        string $subjectType = 'Subject1',
        int $pageOffset = 0,
        int $pageSize = 100,
    ): array {
        $response = $this->json($this->request($this->accessToken())->post(
            '/invoices/query/metadata?'.http_build_query(['pageOffset' => $pageOffset, 'pageSize' => $pageSize]),
            [
                'subjectType' => $subjectType,
                'dateRange' => [
                    'dateType' => 'Issue',
                    'from' => $from->toIso8601String(),
                    'to' => $to->toIso8601String(),
                ],
            ],
        ));

        return [
            'invoices' => $response['invoices'] ?? [],
            'hasMore' => (bool) ($response['hasMore'] ?? false),
        ];
    }

    /** Treść faktury (XML) spod numeru KSeF. */
    public function downloadInvoice(string $ksefNumber): string
    {
        $response = $this->request($this->accessToken())
            ->accept('application/xml')
            ->get('/invoices/ksef/'.$ksefNumber);

        if ($response->failed()) {
            throw new KsefException('Nie udało się pobrać faktury '.$ksefNumber.': HTTP '.$response->status(), $response->status());
        }

        return $response->body();
    }

    /**
     * Certyfikat klucza publicznego do szyfrowania tokena. Klucze bywają rotowane,
     * więc trzymamy je w cache tylko na dobę.
     *
     * @return array{certificate: string, publicKeyId: string}
     */
    public function tokenEncryptionKey(): array
    {
        return $this->publicKeyCertificate('KsefTokenEncryption');
    }

    /**
     * Certyfikat klucza publicznego o danym przeznaczeniu: „KsefTokenEncryption"
     * dla tokena, „SymmetricKeyEncryption" dla klucza szyfrującego faktury.
     *
     * @return array{certificate: string, publicKeyId: string}
     */
    public function publicKeyCertificate(string $usage): array
    {
        $certificates = Cache::remember(
            'ksef.public-keys.'.($this->settings->environment ?? KsefEnvironment::Test)->value,
            now()->addDay(),
            fn () => $this->json($this->request()->get('/security/public-key-certificates')),
        );

        foreach ($certificates as $certificate) {
            if (in_array($usage, (array) ($certificate['usage'] ?? []), true)) {
                return $certificate;
            }
        }

        throw new KsefException('KSeF nie udostępnił klucza o przeznaczeniu '.$usage.'.');
    }

    /**
     * @return array{0: string, 1: string|null} szyfrogram w Base64 oraz identyfikator użytego klucza
     */
    private function encryptToken(string $token, int $timestampMs): array
    {
        return $this->encryptWithPublicKey($token.'|'.$timestampMs, 'KsefTokenEncryption');
    }

    private function awaitAuthentication(string $reference, string $operationToken): void
    {
        for ($attempt = 1; $attempt <= self::STATUS_ATTEMPTS; $attempt++) {
            $status = $this->json($this->request($operationToken)->get('/auth/'.$reference));
            $code = (int) ($status['status']['code'] ?? 0);

            if ($code === 200) {
                return;
            }

            if ($code >= 400) {
                throw new KsefException(
                    'Uwierzytelnienie odrzucone przez KSeF: '.($status['status']['description'] ?? 'kod '.$code),
                );
            }

            usleep(self::STATUS_DELAY_MS * 1000);
        }

        throw new KsefException('KSeF nie potwierdził uwierzytelnienia w wyznaczonym czasie.');
    }

    private function request(?string $bearer = null): PendingRequest
    {
        $request = Http::baseUrl($this->settings->baseUrl())
            ->acceptJson()
            ->timeout(30);

        return $bearer ? $request->withToken($bearer) : $request;
    }

    /**
     * Odpowiedzi błędów KSeF niosą opis w polu `exception`, więc wyciągamy go
     * zamiast pokazywać użytkownikowi surowy kod HTTP.
     */
    private function json(Response $response): array
    {
        if ($response->failed()) {
            $body = $response->json() ?? [];

            $details = collect($body['exception']['exceptionDetailList'] ?? [])
                ->map(fn ($detail) => trim(($detail['exceptionDescription'] ?? '').' '.implode(' ', (array) ($detail['details'] ?? []))))
                ->filter()
                ->implode(' ');

            $message = $details
                ?: ($body['exception']['exceptionDescription']
                ?? $body['message']
                ?? $body['title']
                ?? 'HTTP '.$response->status());

            throw new KsefException('KSeF odrzucił żądanie: '.$message, $response->status());
        }

        return (array) $response->json();
    }

    /** @throws KsefException */
    public static function wrapConnectionErrors(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ConnectionException $e) {
            throw new KsefException('Brak połączenia z KSeF: '.$e->getMessage());
        }
    }
}
