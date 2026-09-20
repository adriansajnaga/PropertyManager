<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    public const SOURCE_MANUAL = 'manual';

    /** Stan licznika wyliczony na granicę miesiąca, zapisany, by kolejne rozliczenie ruszyło z tego samego punktu. */
    public const SOURCE_CALCULATED = 'calculated';

    protected $fillable = [
        'meter_id',
        'source_table',
        'source_id',
        'source_meter_serial',
        'source_meter_name',
        'raw_hex',
        'consumption',
        'reading_date',
        'reading_timestamp',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'consumption' => 'decimal:4',
            'reading_date' => 'date',
            'reading_timestamp' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function scopeOrphaned(Builder $query): Builder
    {
        return $query->whereNull('meter_id');
    }

    public function isManual(): bool
    {
        return $this->source_table === self::SOURCE_MANUAL;
    }

    public function isCalculated(): bool
    {
        return $this->source_table === self::SOURCE_CALCULATED;
    }

    public function sourceLabel(): string
    {
        return match (true) {
            $this->isManual() => 'ręczny',
            $this->isCalculated() => 'obliczony',
            default => 'automatyczny',
        };
    }
}
