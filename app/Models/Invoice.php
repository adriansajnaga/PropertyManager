<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'number',
        'document_type',
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
        'emailed_at',
        'emailed_to',
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
            'document_type' => DocumentType::class,
            'status' => InvoiceStatus::class,
            'ksef_sent_at' => 'datetime',
            'emailed_at' => 'datetime',
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

    public function wasEmailed(): bool
    {
        return $this->emailed_at !== null;
    }

    public function isReceipt(): bool
    {
        return $this->document_type === DocumentType::Receipt;
    }

    /** Rachunek nie ma czego szukać w KSeF. */
    public function goesToKsef(): bool
    {
        return ($this->document_type ?? DocumentType::Invoice)->goesToKsef();
    }

    public function title(): string
    {
        return ($this->document_type ?? DocumentType::Invoice)->title().' '.$this->number;
    }
}
