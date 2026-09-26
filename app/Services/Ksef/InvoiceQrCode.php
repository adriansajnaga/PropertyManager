<?php

namespace App\Services\Ksef;

use App\Enums\KsefEnvironment;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\KsefSetting;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Kod QR weryfikujący fakturę (KOD I). Link prowadzi do KSeF i pozwala każdemu
 * sprawdzić, czy dokument tam jest i czy nie został zmieniony:
 * {adres}/invoice/{NIP sprzedawcy}/{DD-MM-RRRR}/{SHA-256 XML w Base64URL}
 */
class InvoiceQrCode
{
    /** Link weryfikacyjny — tylko dla faktur, których XML trafił do KSeF. */
    public function url(Invoice $invoice): ?string
    {
        if (blank($invoice->xml)) {
            return null;
        }

        $nip = preg_replace('/\D+/', '', (string) InvoiceSetting::current()->seller_nip);
        // Środowisko z chwili wysyłki — po przełączeniu ustawień na produkcję
        // stara faktura nadal ma być sprawdzalna tam, gdzie ją przyjęto.
        $environment = $invoice->ksef_environment
            ?? KsefSetting::current()->environment
            ?? KsefEnvironment::Test;

        return sprintf(
            '%s/invoice/%s/%s/%s',
            $environment->qrBaseUrl(),
            $nip,
            $invoice->issued_on->format('d-m-Y'),
            $this->base64Url(hash('sha256', $invoice->xml, true)),
        );
    }

    /** Kod QR jako obrazek do osadzenia w PDF. */
    public function dataUri(string $url, int $size = 220): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 0), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($url));
    }

    private function base64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
