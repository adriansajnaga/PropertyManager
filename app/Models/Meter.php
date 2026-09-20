<?php

namespace App\Models;

use App\Enums\MeterType;
use App\Services\ReadingSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meter extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'serial_number',
        'name',
        'model',
        'is_active',
        'is_main',
        'is_boiler_supply',
    ];

    protected function casts(): array
    {
        return [
            'type' => MeterType::class,
            'is_active' => 'boolean',
            'is_main' => 'boolean',
            'is_boiler_supply' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Nowy licznik (lub poprawiony numer seryjny) od razu przejmuje odczyty,
        // które spłynęły wcześniej z samym numerem seryjnym.
        static::saved(function (Meter $meter) {
            if ($meter->wasRecentlyCreated || $meter->wasChanged(['serial_number', 'type'])) {
                app(ReadingSyncService::class)->linkOrphans($meter);
            }
        });
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UnitMeterAssignment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, MeterType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function currentUnit(): ?Unit
    {
        $today = now()->toDateString();

        $assignment = $this->assignments()
            ->with('unit.property')
            ->where('valid_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->orderByDesc('valid_from')
            ->first();

        return $assignment?->unit;
    }
}
