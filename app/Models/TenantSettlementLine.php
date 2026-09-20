<?php

namespace App\Models;

use App\Enums\SettlementLineCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSettlementLine extends Model
{
    protected $fillable = [
        'tenant_settlement_id',
        'category',
        'label',
        'meter_serial',
        'meter_model',
        'invoice_number',
        'start_reading',
        'end_reading',
        'start_state',
        'end_state',
        'start_interpolated',
        'end_interpolated',
        'start_date',
        'end_date',
        'consumption',
        'consumption_unit',
        'unit_price',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'category' => SettlementLineCategory::class,
            'start_reading' => 'decimal:4',
            'end_reading' => 'decimal:4',
            'start_state' => 'decimal:4',
            'end_state' => 'decimal:4',
            'start_interpolated' => 'boolean',
            'end_interpolated' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'consumption' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(TenantSettlement::class, 'tenant_settlement_id');
    }
}
