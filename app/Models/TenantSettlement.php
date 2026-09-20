<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantSettlement extends Model
{
    protected $fillable = [
        'number',
        'unit_id',
        'tenant_id',
        'water_bill_id',
        'electric_bill_id',
        'month',
        'status',
        'vat_rate',
        'total_net',
        'total_gross',
        'pdf_path',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'status' => SettlementStatus::class,
            'vat_rate' => 'decimal:4',
            'total_net' => 'decimal:2',
            'total_gross' => 'decimal:2',
            'generated_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function waterBill(): BelongsTo
    {
        return $this->belongsTo(UtilityBill::class, 'water_bill_id');
    }

    public function electricBill(): BelongsTo
    {
        return $this->belongsTo(UtilityBill::class, 'electric_bill_id');
    }

    /**
     * @return array<string, int|null>
     */
    public function billIds(): array
    {
        return [
            'water' => $this->water_bill_id,
            'electric' => $this->electric_bill_id,
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TenantSettlementLine::class);
    }

    public function isFinal(): bool
    {
        return $this->status === SettlementStatus::Final;
    }

    public function vatAmount(): float
    {
        return round((float) $this->total_gross - (float) $this->total_net, 2);
    }
}
