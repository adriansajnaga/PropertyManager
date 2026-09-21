<?php

namespace App\Services\Sms;

interface SmsGateway
{
    public function isConfigured(): bool;

    /**
     * @throws SmsException gdy bramka odrzuci wiadomość albo nie odpowiada
     */
    public function send(string $phone, string $message): void;
}
