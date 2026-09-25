<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSetting extends Model
{
    protected $fillable = [
        'seller_name',
        'seller_nip',
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
