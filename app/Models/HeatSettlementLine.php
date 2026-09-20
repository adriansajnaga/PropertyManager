<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeatSettlementLine extends Model
{
    protected $fillable = [
        'heat_settlement_id',
        'meter_id',
        'meter_serial',
        'meter_name',
        'start_reading',
        'end_reading',
        'gj_consumed',
    ];

    protected function casts(): array
    {
        return [
            'start_reading' => 'decimal:4',
            'end_reading' => 'decimal:4',
            'gj_consumed' => 'decimal:4',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(HeatSettlement::class, 'heat_settlement_id');
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }
}
