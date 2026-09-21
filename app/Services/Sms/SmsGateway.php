<?php

namespace App\Services\Sms;

interface SmsGateway
{
    public function isConfigured(): bool;

    /** Tryb testowy: bramka sprawdza wiadomość, ale jej nie wysyła. */
    public function isTestMode(): bool;

    /**
     * @throws SmsException gdy bramka odrzuci wiadomość albo nie odpowiada
     */
    public function send(string $phone, string $message): void;
}
