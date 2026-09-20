<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeatSettlement extends Model
{
    protected $fillable = [
        'month',
        'electric_bill_id',
        'boiler_meter_serial',
        'boiler_meter_name',
        'boiler_start_reading',
        'boiler_end_reading',
        'boiler_kwh_consumed',
        'total_gj_consumed',
        'price_per_gj',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'boiler_start_reading' => 'decimal:4',
            'boiler_end_reading' => 'decimal:4',
            'boiler_kwh_consumed' => 'decimal:4',
            'total_gj_consumed' => 'decimal:4',
            'price_per_gj' => 'decimal:4',
        ];
    }

    public function electricBill(): BelongsTo
    {
        return $this->belongsTo(UtilityBill::class, 'electric_bill_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HeatSettlementLine::class);
    }
}
