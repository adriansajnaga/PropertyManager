<?php

namespace App\Services;

use App\Enums\MeterType;
use App\Enums\SettlementLineCategory;
use App\Enums\SettlementStatus;
use App\Enums\UtilityType;
use App\Models\HeatSettlement;
use App\Models\Meter;
use App\Models\TenantSettlement;
use App\Models\Unit;
use App\Models\UtilityBill;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantSettlementCalculator
{
    public function __construct(private readonly ReadingLookup $readings) {}

    /**
     * Liczy rozliczenie lokalu za miesiąc: media wg zużycia między granicami
     * miesiąca plus proracja rocznych kosztów utrzymania nieruchomości.
     *
     * @param  array<string, int|null>  $billIds  rachunki wybrane ręcznie, kluczem jest typ ('water', 'electric')
     */
    public function preview(Unit $unit, CarbonImmutable $month, array $billIds = []): array
    {
        $start = $month->startOfMonth();
        $end = $start->addMonth();

        $bills = [];
        $billWarnings = [];

        foreach (UtilityType::cases() as $utility) {
            [$bills[$utility->value], $billWarnings[$utility->value]] =
                $this->resolveBill($utility, $start, $billIds[$utility->value] ?? null);
        }

        $lines = [];
        $warnings = [];

        $calculatedStates = [];

        foreach ([MeterType::Water, MeterType::Electric, MeterType::Heat] as $type) {
            [$line, $lineWarnings, $lineStates] = $this->mediaLine(
                $unit, $type, $start, $end,
                $bills[$type->value] ?? null,
                $billWarnings[$type->value] ?? null,
            );

            $calculatedStates = array_merge($calculatedStates, $lineStates);

            if ($line !== null) {
                $lines[] = $line;
            }

            $warnings = array_merge($warnings, $lineWarnings);
        }

        [$costLines, $costWarnings] = $this->maintenanceLines($unit, $start);
        $lines = array_merge($lines, $costLines);
        $warnings = array_merge($warnings, $costWarnings);

        $vatRate = (float) config('pm.vat_rate');
        $totalNet = round(array_sum(array_column($lines, 'amount')), 2);
        $totalGross = round($totalNet * (1 + $vatRate), 2);

        return [
            'unit' => $unit,
            'tenant' => $unit->tenantAt($start->toDateString()),
            'month' => $start->toDateString(),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'lines' => $lines,
            'vat_rate' => $vatRate,
            'total_net' => $totalNet,
            'total_vat' => round($totalGross - $totalNet, 2),
            'total_gross' => $totalGross,
            'bills' => $bills,
            'calculated_states' => $calculatedStates,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, int|null>  $billIds
     */
    public function store(Unit $unit, CarbonImmutable $month, array $billIds = []): TenantSettlement
    {
        $data = $this->preview($unit, $month, $billIds);

        return DB::transaction(function () use ($data, $unit) {
            $existing = TenantSettlement::query()
                ->where('unit_id', $unit->id)
                ->whereDate('month', $data['month'])
                ->first();

            if ($existing?->isFinal()) {
                throw new RuntimeException('Rozliczenie jest zatwierdzone i nie może zostać przeliczone.');
            }

            // Szukamy przez whereDate, bo updateOrCreate porównuje surowy tekst daty,
            // a kolumna bywa zapisana z godziną — wtedy powstawałby duplikat.
            $settlement = $existing ?? new TenantSettlement(['unit_id' => $unit->id, 'month' => $data['month']]);

            $settlement->fill([
                'tenant_id' => $data['tenant']?->id,
                'water_bill_id' => $data['bills']['water']?->id,
                'electric_bill_id' => $data['bills']['electric']?->id,
                'status' => SettlementStatus::Draft,
                'vat_rate' => $data['vat_rate'],
                'total_net' => $data['total_net'],
                'total_gross' => $data['total_gross'],
                'generated_at' => now(),
            ]);

            $settlement->number ??= $this->number($settlement);
            $settlement->save();

            $settlement->lines()->delete();
            $settlement->lines()->createMany($data['lines']);

            // Wyliczone stany na granicach miesiąca zapisujemy jako odczyty, żeby kolejne
            // rozliczenie wystartowało dokładnie tam, gdzie zakończyło się to.
            foreach ($data['calculated_states'] as $state) {
                $this->readings->storeCalculated($state['meter'], $state['date'], $state['value']);
            }

            return $settlement->load('lines', 'unit.property', 'tenant');
        });
    }

    private function mediaLine(
        Unit $unit,
        MeterType $type,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?UtilityBill $bill,
        ?string $billWarning,
    ): array {
        $warnings = [];
        $meter = $unit->meterAt($start->toDateString(), $type);

        if ($meter === null) {
            return [null, ["Lokal nie ma przypisanego licznika: {$type->label()}."], []];
        }

        $startState = $this->readings->stateAt($meter, $start->toDateString());
        $endState = $this->readings->stateAt($meter, $end->toDateString());

        if ($startState['state'] === null || $endState['state'] === null) {
            $warnings[] = "Brak kompletu odczytów licznika {$meter->name} ({$type->label()}) na granicach miesiąca.";
        }

        $calculated = [];

        foreach ([[$startState, $start], [$endState, $end]] as [$state, $boundary]) {
            if ($state['interpolated']) {
                $calculated[] = ['meter' => $meter, 'date' => $boundary->toDateString(), 'value' => $state['state']];

                $warnings[] = sprintf(
                    'Brak odczytu licznika %s (%s) na %s — stan wyliczony między odczytami z %s i %s.',
                    $meter->name,
                    $type->label(),
                    $boundary->format('d.m.Y'),
                    $this->readings->previousFor($meter->id, $boundary->toDateString())?->reading_date->format('d.m.Y'),
                    $this->readings->nextFor($meter->id, $boundary->toDateString())?->reading_date->format('d.m.Y'),
                );
            }
        }

        $consumption = ($startState['state'] !== null && $endState['state'] !== null)
            ? round(max(0, $endState['state'] - $startState['state']), 4)
            : 0.0;

        if ($type === MeterType::Heat) {
            [$price, $priceWarning] = $this->heatPrice($start);
            $invoiceNumber = null;
        } else {
            $price = (float) ($bill?->net_price ?? 0);
            $invoiceNumber = $bill?->invoice_number;
            $priceWarning = $billWarning;
        }

        // Przy zerowym zużyciu brak ceny niczego nie psuje — tak wygląda np. ciepło
        // poza sezonem grzewczym, gdy kotłownia stoi i nie ma rozliczenia kosztów ciepła.
        if ($priceWarning !== null && $consumption > 0) {
            $warnings[] = $priceWarning;
        }

        $category = SettlementLineCategory::from($type->value);

        return [[
            'category' => $category,
            'label' => $type->label(),
            'meter_serial' => $meter->serial_number,
            'meter_model' => $meter->model,
            'invoice_number' => $invoiceNumber,
            'start_reading' => $startState['reading']?->consumption,
            'end_reading' => $endState['reading']?->consumption,
            'start_date' => $startState['reading']?->reading_date,
            'end_date' => $endState['reading']?->reading_date,
            'start_state' => $startState['state'],
            'end_state' => $endState['state'],
            'start_interpolated' => $startState['interpolated'],
            'end_interpolated' => $endState['interpolated'],
            'consumption' => $consumption,
            'consumption_unit' => $type->unit(),
            'unit_price' => $price,
            'amount' => round($consumption * $price, 2),
        ], $warnings, $calculated];
    }

    /**
     * Cena ciepła pochodzi z rozliczenia kosztów kotłowni za ten sam miesiąc (moduł 8).
     */
    private function heatPrice(CarbonImmutable $month): array
    {
        $heatSettlement = HeatSettlement::query()
            ->whereDate('month', $month->toDateString())
            ->first();

        if ($heatSettlement === null) {
            return [0.0, 'Brak rozliczenia kosztów ciepła za '.$month->format('m.Y').' — cena za GJ przyjęta jako 0.'];
        }

        return [(float) $heatSettlement->price_per_gj, null];
    }

    /**
     * Rachunki bywają wystawiane za kilka miesięcy naraz, więc poza rachunkiem
     * wybranym ręcznie i rachunkiem z danego miesiąca sięgamy po ostatni
     * wcześniejszy — z ostrzeżeniem, żeby nie przeszło to niezauważone.
     */
    private function resolveBill(UtilityType $type, CarbonImmutable $month, ?int $billId): array
    {
        $query = fn () => UtilityBill::query()->where('type', $type);

        if ($billId !== null && $chosen = $query()->find($billId)) {
            return [$chosen, null];
        }

        $forMonth = $query()->whereDate('month', $month->toDateString())->latest('id')->first();

        if ($forMonth !== null) {
            return [$forMonth, null];
        }

        $earlier = $query()
            ->whereDate('month', '<', $month->toDateString())
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->first();

        if ($earlier !== null) {
            return [$earlier, sprintf(
                'Brak rachunku (%s) za %s — użyto ostatniego wcześniejszego: %s z %s. Zmień wybór, jeśli to nie ten rachunek.',
                $type->label(), $month->format('m.Y'), $earlier->invoice_number, $earlier->month->format('m.Y'),
            )];
        }

        return [null, "Brak rachunku ({$type->label()}) — cena przyjęta jako 0."];
    }

    /**
     * Roczny koszt ÷ powierzchnia nieruchomości ÷ 12 × powierzchnia lokalu.
     */
    private function maintenanceLines(Unit $unit, CarbonImmutable $month): array
    {
        $property = $unit->property;
        $totalArea = (float) $property->total_area;

        if ($totalArea <= 0) {
            return [[], ["Nieruchomość {$property->name} ma zerową powierzchnię — koszty utrzymania pominięte."]];
        }

        $costs = $property->maintenanceCostsForYear((int) $month->year)->get();

        if ($costs->isEmpty()) {
            return [[], ['Brak rocznych kosztów utrzymania za '.$month->year.'.']];
        }

        $lines = $costs->map(function ($cost) use ($totalArea, $unit) {
            $monthlyPerM2 = (float) $cost->annual_cost / $totalArea / 12;

            return [
                'category' => SettlementLineCategory::MaintenanceCost,
                'label' => $cost->description,
                'meter_serial' => null,
                'meter_model' => null,
                'invoice_number' => null,
                'start_reading' => null,
                'end_reading' => null,
                'start_date' => null,
                'end_date' => null,
                'start_state' => null,
                'end_state' => null,
                'start_interpolated' => false,
                'end_interpolated' => false,
                'consumption' => (float) $unit->area,
                'consumption_unit' => 'm²',
                'unit_price' => round($monthlyPerM2, 4),
                'amount' => round($monthlyPerM2 * (float) $unit->area, 2),
            ];
        })->all();

        return [$lines, []];
    }

    private function number(TenantSettlement $settlement): string
    {
        return sprintf(
            '%s/%s/%d',
            config('pm.settlement_number_prefix'),
            $settlement->month->format('Y-m'),
            $settlement->unit_id,
        );
    }
}
