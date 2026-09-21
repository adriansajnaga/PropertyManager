<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Bramka SMSAPI.pl. Zmiana dostawcy SMS oznacza napisanie drugiej takiej klasy
 * i podpięcie jej w AppServiceProvider — reszta aplikacji zna tylko SmsGateway.
 */
class SmsapiGateway implements SmsGateway
{
    private const ENDPOINT = 'https://api.smsapi.pl/sms.do';

    public function __construct(
        private readonly ?string $token,
        private readonly ?string $sender = null,
        private readonly bool $test = false,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->token);
    }

    public function send(string $phone, string $message): void
    {
        if (! $this->isConfigured()) {
            throw new SmsException('Bramka SMS nie jest skonfigurowana — brak SMSAPI_TOKEN w pliku .env.');
        }

        $params = array_filter([
            'to' => $phone,
            'message' => $message,
            // Pole nadawcy musi być zatwierdzone w panelu SMSAPI, inaczej zostaw puste.
            'from' => $this->sender,
            'format' => 'json',
            'encoding' => 'utf-8',
            // Polskie znaki zamieniane na łacińskie — SMS mieści 160 znaków zamiast 70.
            'normalize' => '1',
            // Tryb testowy: SMSAPI sprawdza wiadomość, ale jej nie wysyła i nie pobiera opłaty.
            'test' => $this->test ? '1' : null,
        ], fn ($value) => filled($value));

        try {
            $response = Http::withToken($this->token)
                ->asForm()
                ->timeout(15)
                ->post(self::ENDPOINT, $params);
        } catch (ConnectionException $e) {
            throw new SmsException('Brak połączenia z bramką SMS: '.$e->getMessage(), previous: $e);
        }

        $body = $response->json() ?? [];

        // SMSAPI potrafi zgłosić błąd w treści odpowiedzi przy statusie HTTP 200.
        if ($response->failed() || isset($body['error'])) {
            throw new SmsException(sprintf(
                'Bramka SMS odrzuciła wiadomość: %s%s',
                $body['message'] ?? 'HTTP '.$response->status(),
                isset($body['error']) ? ' (kod '.$body['error'].')' : '',
            ));
        }
    }
}
