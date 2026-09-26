<?php

namespace App\Models;

use App\Services\ReadingSyncService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    public const SOURCE_MANUAL = 'manual';

    /** Stan licznika wyliczony na granicę miesiąca, zapisany, by kolejne rozliczenie ruszyło z tego samego punktu. */
    public const SOURCE_CALCULATED = 'calculated';

    /** Stan wodomierza przeliczony z odczytu nakładki radiowej o różnicę wskazań. */
    public const SOURCE_MODULE = 'module';

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
     * Moment odczytu — wyłącznie z `reading_date`. Kolumna `reading_timestamp`
     * mówi, kiedy wiersz trafił na serwer, a to bywa zupełnie inna godzina,
     * więc nie wolno jej podstawiać w miejsce momentu odczytu.
     */
    public function measuredAt(): CarbonInterface
    {
        return $this->reading_date;
    }

    /** Kiedy odczyt trafił do bazy — do podpowiedzi przy dacie, nie do rozliczeń. */
    public function recordedAtLabel(): ?string
    {
        return $this->reading_timestamp?->format('d.m.Y, H:i');
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

    public function isFromModule(): bool
    {
        return $this->source_table === self::SOURCE_MODULE;
    }

    /**
     * Jednostka odczytu — bierzemy ją z licznika, a gdy odczyt jeszcze nie ma
     * przypisania, z medium wskazanego przez źródło (nazwa tabeli albo parametr
     * w linku kolektora). Bez tego zostaje sama liczba.
     */
    public function unit(): ?string
    {
        return $this->meter?->type->unit()
            ?? ReadingSyncService::mediumOf($this->source_table)?->unit();
    }

    public function sourceLabel(): string
    {
        return match (true) {
            $this->isManual() => 'ręczny',
            $this->isCalculated() => 'obliczony',
            $this->isFromModule() => 'z nakładki',
            default => 'automatyczny',
        };
    }
}
