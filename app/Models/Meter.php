<?php

namespace App\Models;

use App\Enums\MeterType;
use App\Services\ModuleReadingConverter;
use App\Services\ReadingSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'is_module',
        'module_for_meter_id',
        'module_offset',
    ];

    protected function casts(): array
    {
        return [
            'type' => MeterType::class,
            'is_active' => 'boolean',
            'is_main' => 'boolean',
            'is_boiler_supply' => 'boolean',
            'is_module' => 'boolean',
            'module_offset' => 'decimal:4',
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

            // Zmiana różnicy wskazań albo przypisania nakładki przelicza jej odczyty od nowa.
            if ($meter->is_module && $meter->wasChanged(['module_offset', 'module_for_meter_id', 'is_module'])) {
                app(ModuleReadingConverter::class)->convertFor($meter->fresh());
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

    /** Licznik mechaniczny, na którym siedzi ta nakładka. */
    public function moduleFor(): BelongsTo
    {
        return $this->belongsTo(Meter::class, 'module_for_meter_id');
    }

    /** Nakładki radiowe zamontowane na tym liczniku. */
    public function modules(): HasMany
    {
        return $this->hasMany(Meter::class, 'module_for_meter_id');
    }

    /** Nakładka nie jest licznikiem lokalu — do rozliczeń idzie licznik, na którym siedzi. */
    public function scopeWithoutModules(Builder $query): Builder
    {
        return $query->where('is_module', false);
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
