<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Reading;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Kontrola dziennych odczytów: czy każdy czynny licznik przysłał dziś swój stan.
 * Nakładki radiowe pomijamy — ich odczyt jest przeliczany na licznik, pod którym
 * siedzą, więc to ten licznik ma się pojawić w zestawieniu.
 */
class ReadingWatchdog
{
    /**
     * @return array{
     *     day: CarbonImmutable,
     *     expected: int,
     *     reported: int,
     *     missing: Collection<int, Meter>,
     *     orphans: int
     * }
     */
    public function report(?CarbonInterface $day = null): array
    {
        $day = CarbonImmutable::parse($day ?? now())->startOfDay();

        $expected = Meter::active()->withoutModules()->orderBy('name')->get();

        $reported = Reading::query()
            ->whereNotNull('meter_id')
            ->whereDate('reading_date', $day->toDateString())
            ->distinct()
            ->pluck('meter_id');

        $missing = $expected
            ->reject(fn (Meter $meter) => $reported->contains($meter->id))
            ->values();

        return [
            'day' => $day,
            'expected' => $expected->count(),
            'reported' => $expected->count() - $missing->count(),
            'missing' => $missing,
            // Odczyt, który dziś przyszedł, ale nie dopasował się do żadnego licznika
            // po numerze seryjnym — bywa przyczyną brakującego stanu.
            'orphans' => Reading::orphaned()->whereDate('reading_date', $day->toDateString())->count(),
        ];
    }

    /** Ostatni znany stan licznika — żeby w e-mailu było widać, od kiedy milczy. */
    public function lastReadingAt(Meter $meter): ?CarbonInterface
    {
        return $meter->readings()->max('reading_date')
            ? CarbonImmutable::parse($meter->readings()->max('reading_date'))
            : null;
    }
}
