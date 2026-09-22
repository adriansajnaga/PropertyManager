<?php

namespace App\Models;

use Carbon\CarbonInterface;
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
            'reading_date' => 'datetime',
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

    /** Godzina jest znana, gdy kolektor ją zapisał; wpis ręczny i odczyt obliczony jej nie mają. */
    public function hasTime(): bool
    {
        return $this->measuredAt()->format('H:i:s') !== '00:00:00';
    }

    /**
     * Moment odczytu. Kolektor zapisuje godzinę wprost w `reading_date`,
     * starsze wiersze mają ją osobno w `reading_timestamp`.
     */
    public function measuredAt(): CarbonInterface
    {
        if ($this->reading_date !== null && $this->reading_date->format('H:i:s') !== '00:00:00') {
            return $this->reading_date;
        }

        return $this->reading_timestamp ?? $this->reading_date;
    }

    /** Data z godziną, jeśli jest znana — inaczej sama data. */
    public function measuredAtLabel(): string
    {
        return $this->measuredAt()->format($this->hasTime() ? 'd.m.Y, H:i' : 'd.m.Y');
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
