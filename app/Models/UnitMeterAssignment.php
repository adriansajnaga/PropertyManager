<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitMeterAssignment extends Model
{
    protected $fillable = [
        'unit_id',
        'meter_id',
        'valid_from',
        'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }
}
