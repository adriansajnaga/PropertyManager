<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Reading;
use Illuminate\Support\Collection;

class ReadingLookup
{
    /**
     * Ostatni odczyt licznika nie późniejszy niż podana data.
     * Granice miesiąca rzadko trafiają w dokładny moment odczytu, więc bierzemy
     * najbliższy odczyt wstecz zamiast wymagać trafienia co do dnia.
     */
    public function nearestBefore(Meter $meter, string $date): ?Reading
    {
        return $this->previousFor($meter->id, $date);
    }

    /**
     * Stan licznika na dany dzień. Liczniki rzadko są odczytywane dokładnie
     * pierwszego dnia miesiąca, więc gdy odczyt wypada obok granicy, stan na
     * granicę wyliczamy liniowo między odczytem wcześniejszym a późniejszym.
     * Zwracany `reading` to rzeczywisty odczyt (najbliższy granicy) — trafia na
     * rozliczenie jako informacja, skąd wzięła się wartość.
     *
     * @return array{state: float|null, reading: Reading|null, interpolated: bool}
     */
    public function stateAt(Meter $meter, string $date): array
    {
        $previous = $this->previousFor($meter->id, $date);
        $next = $this->nextFor($meter->id, $date);

        if ($previous !== null && $previous->reading_date->isSameDay($date)) {
            return ['state' => (float) $previous->consumption, 'reading' => $previous, 'interpolated' => false];
        }

        if ($previous !== null && $next !== null) {
            $span = $previous->reading_date->diffInDays($next->reading_date);
            $elapsed = $previous->reading_date->diffInDays($date);

            $state = $span > 0
                ? (float) $previous->consumption
                    + (((float) $next->consumption - (float) $previous->consumption) * $elapsed / $span)
                : (float) $previous->consumption;

            $closer = $elapsed <= ($span - $elapsed) ? $previous : $next;

            return ['state' => round($state, 4), 'reading' => $closer, 'interpolated' => true];
        }

        if ($previous !== null) {
            return ['state' => (float) $previous->consumption, 'reading' => $previous, 'interpolated' => false];
        }

        // Przed pierwszym odczytem licznika nie ma z czego liczyć — nie zgadujemy.
        return ['state' => null, 'reading' => $next, 'interpolated' => false];
    }

    /**
     * Zapisuje wyliczony stan licznika na granicę miesiąca. Dzięki temu kolejne
     * rozliczenie zaczyna się dokładnie tam, gdzie skończyło się poprzednie.
     */
    public function storeCalculated(Meter $meter, string $date, float $value): Reading
    {
        $reading = Reading::query()
            ->where('meter_id', $meter->id)
            ->where('source_table', Reading::SOURCE_CALCULATED)
            ->whereDate('reading_date', $date)
            ->first() ?? new Reading([
                'meter_id' => $meter->id,
                'source_table' => Reading::SOURCE_CALCULATED,
                'reading_date' => $date,
            ]);

        $reading->fill([
            'source_meter_serial' => $meter->serial_number,
            'source_meter_name' => $meter->name,
            'consumption' => $value,
        ])->save();

        return $reading;
    }

    public function previousFor(int $meterId, string $date, ?int $exceptId = null): ?Reading
    {
        return Reading::query()
            ->where('meter_id', $meterId)
            ->whereDate('reading_date', '<=', $date)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderByDesc('reading_date')
            ->orderByDesc('reading_timestamp')
            ->orderByDesc('id')
            ->first();
    }

    public function nextFor(int $meterId, string $date, ?int $exceptId = null): ?Reading
    {
        return Reading::query()
            ->where('meter_id', $meterId)
            ->whereDate('reading_date', '>', $date)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderBy('reading_date')
            ->orderBy('reading_timestamp')
            ->orderBy('id')
            ->first();
    }

    /**
     * Po kilka najnowszych odczytów dla każdego licznika jednym zapytaniem.
     *
     * @return Collection<int, Collection<int, Reading>> kluczem jest meter_id
     */
    public function recentForMeters(iterable $meterIds, int $limit = 5): Collection
    {
        $ranked = Reading::query()
            ->select(['id', 'meter_id', 'consumption', 'reading_date', 'source_table'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY meter_id ORDER BY reading_date DESC, id DESC) AS position')
            ->whereIn('meter_id', collect($meterIds)->all());

        return Reading::query()
            ->fromSub($ranked, 'readings')
            ->where('position', '<=', $limit)
            ->orderBy('meter_id')
            ->orderBy('position')
            ->get()
            ->groupBy('meter_id');
    }
}
