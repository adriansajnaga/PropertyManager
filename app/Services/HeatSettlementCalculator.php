<?php

namespace App\Services;

use App\Enums\MeterType;
use App\Models\HeatSettlement;
use App\Models\Meter;
use App\Models\UtilityBill;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class HeatSettlementCalculator
{
    public function __construct(private readonly ReadingLookup $readings) {}

    /**
     * Wylicza cenę za 1 GJ dla danego miesiąca:
     * (cena kWh × zużycie prądu kotłowni) / suma GJ ze wszystkich liczników ciepła.
     */
    public function preview(CarbonImmutable $month, UtilityBill $electricBill): array
    {
        $start = $month->startOfMonth();
        $end = $start->addMonth();

        $lines = [];
        $totalGj = 0.0;
        $calculatedStates = [];

        foreach ($this->heatMeters() as $meter) {
            // Stan na granicę miesiąca, w razie potrzeby wyliczony między odczytami.
            $startState = $this->readings->stateAt($meter, $start->toDateString());
            $endState = $this->readings->stateAt($meter, $end->toDateString());

            foreach ([[$startState, $start], [$endState, $end]] as [$state, $boundary]) {
                if ($state['interpolated']) {
                    $calculatedStates[] = ['meter' => $meter, 'date' => $boundary->toDateString(), 'value' => $state['state']];
                }
            }

            $consumed = $this->difference($startState['state'], $endState['state']);
            $totalGj += $consumed;

            $lines[] = [
                'meter_id' => $meter->id,
                'meter_serial' => $meter->serial_number,
                'meter_name' => $meter->name,
                'start_reading' => $startState['state'],
                'end_reading' => $endState['state'],
                'gj_consumed' => $consumed,
            ];
        }

        $boilerMeter = $this->boilerMeter();
        $boilerStates = [];

        if ($boilerMeter !== null) {
            foreach ([$start, $end] as $boundary) {
                $state = $this->readings->stateAt($boilerMeter, $boundary->toDateString());
                $boilerStates[] = $state['state'];

                if ($state['interpolated']) {
                    $calculatedStates[] = ['meter' => $boilerMeter, 'date' => $boundary->toDateString(), 'value' => $state['state']];
                }
            }
        }

        [$boilerStart, $boilerEnd] = $boilerStates + [null, null];
        $boilerKwh = $this->difference($boilerStart, $boilerEnd);

        // Cena za GJ to cena, więc zaokrąglamy ją do groszy — i taką samą wartością
        // liczone są potem rozliczenia najemców.
        $pricePerGj = $totalGj > 0
            ? round(((float) $electricBill->net_price * $boilerKwh) / $totalGj, 2)
            : 0.0;

        return [
            'month' => $start->toDateString(),
            'electric_bill' => $electricBill,
            'boiler_meter' => $boilerMeter,
            'boiler_start_reading' => $boilerStart,
            'boiler_end_reading' => $boilerEnd,
            'boiler_kwh_consumed' => $boilerKwh,
            'total_gj_consumed' => round($totalGj, 4),
            'price_per_gj' => $pricePerGj,
            'lines' => $lines,
            'calculated_states' => $calculatedStates,
            'warnings' => $this->warnings($boilerMeter, $totalGj, $lines),
        ];
    }

    public function store(CarbonImmutable $month, UtilityBill $electricBill): HeatSettlement
    {
        $data = $this->preview($month, $electricBill);

        return DB::transaction(function () use ($data, $electricBill) {
            // whereDate zamiast updateOrCreate — ten sam powód co w TenantSettlementCalculator::store().
            $settlement = HeatSettlement::query()->whereDate('month', $data['month'])->first()
                ?? new HeatSettlement(['month' => $data['month']]);

            $settlement->fill([
                'electric_bill_id' => $electricBill->id,
                'boiler_meter_serial' => $data['boiler_meter']?->serial_number,
                'boiler_meter_name' => $data['boiler_meter']?->name,
                'boiler_start_reading' => $data['boiler_start_reading'],
                'boiler_end_reading' => $data['boiler_end_reading'],
                'boiler_kwh_consumed' => $data['boiler_kwh_consumed'],
                'total_gj_consumed' => $data['total_gj_consumed'],
                'price_per_gj' => $data['price_per_gj'],
            ])->save();

            $settlement->lines()->delete();
            $settlement->lines()->createMany($data['lines']);

            foreach ($data['calculated_states'] as $state) {
                $this->readings->storeCalculated($state['meter'], $state['date'], $state['value']);
            }

            return $settlement->load('lines');
        });
    }

    public function heatMeters()
    {
        return Meter::query()
            ->active()
            ->ofType(MeterType::Heat)
            ->where('is_main', false)
            ->orderBy('name')
            ->get();
    }

    /**
     * Kotłownia ma własny podlicznik prądu, oznaczony przy liczniku. Nazwa z config/pm.php
     * pozostaje jako zapasowe dopasowanie dla liczników sprzed wprowadzenia znacznika.
     */
    public function boilerMeter(): ?Meter
    {
        return Meter::query()
            ->ofType(MeterType::Electric)
            ->where('is_boiler_supply', true)
            ->first()
            ?? Meter::query()
                ->ofType(MeterType::Electric)
                ->where('name', config('pm.boiler_meter_name'))
                ->first();
    }

    private function difference(?float $start, ?float $end): float
    {
        if ($start === null || $end === null) {
            return 0.0;
        }

        return round(max(0, $end - $start), 4);
    }

    private function warnings(?Meter $boilerMeter, float $totalGj, array $lines): array
    {
        $warnings = [];

        if ($boilerMeter === null) {
            $warnings[] = 'Nie znaleziono podlicznika prądu kotłowni — potrzebny jest licznik prądu o nazwie "'.config('pm.boiler_meter_name').'".';
        }

        if ($totalGj <= 0) {
            $warnings[] = 'Suma zużycia liczników ciepła wynosi 0 — cena za GJ nie może zostać wyliczona.';
        }

        foreach ($lines as $line) {
            if ($line['start_reading'] === null || $line['end_reading'] === null) {
                $warnings[] = "Brak kompletu odczytów dla licznika {$line['meter_name']} ({$line['meter_serial']}).";
            }
        }

        return $warnings;
    }
}
