<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSetting extends Model
{
    protected $fillable = [
        'seller_name',
        'seller_nip',
        'seller_regon',
        'logo_path',
        'seller_address_l1',
        'seller_address_l2',
        'seller_phone',
        'bank_account',
        'bank_swift',
        'issue_place',
        'payment_days',
        'vat_rate',
        'rent_is_gross',
        'line_description',
        'receipt_issuer_name',
        'receipt_address_l1',
        'receipt_address_l2',
        'receipt_identifier',
        'receipt_note',
    ];

    protected function casts(): array
    {
        return [
            'payment_days' => 'integer',
            'vat_rate' => 'decimal:2',
            'rent_is_gross' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::first() ?? new static;
    }

    public function isConfigured(): bool
    {
        return filled($this->seller_name) && filled($this->seller_nip);
    }

    /** Logo jako data URI — dompdf nie musi wtedy sięgać po plik z dysku. */
    public function logoDataUri(): ?string
    {
        if (blank($this->logo_path) || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($this->logo_path)) {
            return null;
        }

        $contents = \Illuminate\Support\Facades\Storage::disk('local')->get($this->logo_path);

        // Typ bierzemy z zawartości, nie z rozszerzenia — plik bywa wgrany z inną nazwą.
        $mime = match (true) {
            str_starts_with($contents, "PNG") => 'image/png',
            str_starts_with($contents, 'GIF8') => 'image/gif',
            str_starts_with(substr($contents, 8), 'WEBP') => 'image/webp',
            default => 'image/jpeg',
        };

        if (! \App\Support\InvoiceLogo::renderable($mime)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    /**
     * Nazwa pliku PDF w konwencji używanej dotąd: „2026_3_2 ASCOMM.pdf" —
     * rok, numer kolejny, miesiąc i skrót wystawcy.
     */
    public function documentFileName(\App\Models\Invoice $invoice): string
    {
        $brand = str($this->seller_name ?: config('pm.brand_owner'))->before(' ')->upper();

        if (preg_match('#^(\d+)/(\d+)/(\d{4})$#', (string) $invoice->number, $parts)) {
            return sprintf('%s_%s_%s %s.pdf', $parts[3], $parts[1], $parts[2], $brand);
        }

        return str($invoice->document_type->label().'-'.$invoice->number)->slug().'.pdf';
    }

    /** Rachunek wystawiamy jako osoba fizyczna, więc ma osobne dane wystawcy. */
    public function receiptIsConfigured(): bool
    {
        return filled($this->receipt_issuer_name);
    }

    /** Opis pozycji faktury za czynsz, np. „Czynsz wrzesień 2026". */
    public function rentLineDescription(\Carbon\CarbonInterface $month): string
    {
        return str_replace(
            ['{miesiac}', '{rok}'],
            [$month->isoFormat('MMMM'), $month->format('Y')],
            $this->line_description ?: 'Czynsz {miesiac} {rok}',
        );
    }
}
