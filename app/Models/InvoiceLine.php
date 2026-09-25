<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $fillable = [
        'position',
        'name',
        'unit',
        'quantity',
        'unit_price_net',
        'vat_rate',
        'net',
        'vat',
        'gross',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price_net' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'net' => 'decimal:2',
            'vat' => 'decimal:2',
            'gross' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
