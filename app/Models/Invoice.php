<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'number',
        'tenant_id',
        'unit_id',
        'rent_charge_id',
        'issued_on',
        'sold_on',
        'due_on',
        'vat_rate',
        'total_net',
        'total_vat',
        'total_gross',
        'status',
        'ksef_number',
        'ksef_reference',
        'ksef_sent_at',
        'ksef_error',
        'xml',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'sold_on' => 'date',
            'due_on' => 'date',
            'vat_rate' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_vat' => 'decimal:2',
            'total_gross' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'ksef_sent_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function rentCharge(): BelongsTo
    {
        return $this->belongsTo(RentCharge::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    public function isInKsef(): bool
    {
        return filled($this->ksef_number);
    }
}
