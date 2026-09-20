<?php

namespace Database\Seeders;

use App\Enums\MeterType;
use App\Enums\UtilityType;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\Unit;
use App\Models\UtilityBill;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Dane przepisane z archiwalnych rozliczeń „Rozliczenie Lokalu Piaskowa 1-7"
 * (lokale 1 i 2, okresy 01.2026–08.2026): liczniki ciepła i wody wraz z odczytami
 * oraz faktury za prąd i wodę.
 *
 * Lokale rozpoznajemy po przypisanym liczniku prądu, a nie po opisie, bo opis
 * bywa zmieniany ręcznie.
 */
class PiaskowaLokale12Seeder extends Seeder
{
    private const UNITS = [
        'CB01744' => [
            'heat' => ['serial' => '67609520', 'name' => 'Lokal 1 — Ciepło', 'model' => 'Qundis Q Heat 5.5'],
            'water' => ['serial' => '73206437', 'name' => 'Lokal 1 — Woda', 'model' => 'Apator JS16-02'],
            'readings' => [
                'heat' => [
                    '2026-01-01' => 2.634, '2026-02-01' => 4.1473, '2026-03-01' => 5.4254,
                    '2026-04-01' => 6.1032, '2026-05-01' => 6.5657, '2026-06-01' => 6.6975,
                ],
                'water' => [
                    '2026-01-01' => 4.954, '2026-02-01' => 5.932, '2026-03-01' => 6.359,
                    '2026-04-01' => 6.786, '2026-05-01' => 7.213, '2026-06-01' => 7.641,
                    '2026-07-01' => 8.012, '2026-08-01' => 8.423,
                ],
            ],
        ],
        'CB00836' => [
            'heat' => ['serial' => '67609523', 'name' => 'Lokal 2 — Ciepło', 'model' => 'Qundis Q Heat 5.5'],
            'water' => ['serial' => '79640869', 'name' => 'Lokal 2 — Woda', 'model' => 'Apator JS16-02'],
            'readings' => [
                'heat' => [
                    '2026-01-01' => 3.8888, '2026-02-01' => 5.9746, '2026-03-01' => 7.9235,
                    '2026-04-01' => 8.8568, '2026-05-01' => 9.6368, '2026-06-01' => 9.8265,
                ],
                'water' => [
                    '2026-01-01' => 0.967, '2026-02-01' => 1.137, '2026-03-01' => 2.382,
                    '2026-04-01' => 3.626, '2026-05-01' => 4.871, '2026-06-01' => 6.115,
                    '2026-07-01' => 6.341, '2026-08-01' => 6.583,
                ],
            ],
        ],
    ];

    /**
     * [rodzaj, numer faktury, miesiąc od którego obowiązuje, zużycie, kwota netto z faktury]
     *
     * Cena jednostkowa liczona jest jako kwota ÷ zużycie. Na archiwalnych rozliczeniach
     * widnieje zaokrąglona do groszy (np. 0,99 zł/kWh), ale same kwoty wyliczono ceną
     * dokładną — np. koszt prądu pompy ciepła 441,93 zł wychodzi dopiero przy 0,9947 zł/kWh.
     */
    private const BILLS = [
        ['electric', '1269270427/FES/00015', '2026-01-01', 938.00, 933.00],
        ['electric', '1269270427/FES/00016', '2026-02-01', 990.00, 991.38],
        ['electric', '1269270427/FES/00017', '2026-04-01', 346.00, 401.42],
        ['electric', '1269270427/FES/00018', '2026-06-01', 126.00, 194.94],
        ['water', '13219/2025', '2026-01-01', 6.00, 37.12],
        ['water', '15970/2026', '2026-06-01', 10.00, 64.24],
    ];

    public function run(): void
    {
        foreach (self::UNITS as $electricSerial => $config) {
            $unit = $this->unitByElectricMeter($electricSerial);

            foreach ([MeterType::Heat, MeterType::Water] as $type) {
                $spec = $config[$type->value];
                $meter = $this->meter($type, $spec);

                $unit->meterAssignments()->firstOrCreate(
                    ['meter_id' => $meter->id],
                    ['valid_from' => '2026-01-01'],
                );

                foreach ($config['readings'][$type->value] as $date => $value) {
                    $this->reading($meter, $date, $value);
                }
            }
        }

        // Pompa ciepła ma własny podlicznik prądu — po nim liczona jest cena za GJ.
        Meter::where('type', MeterType::Electric)
            ->where('serial_number', 'CB01756')
            ->update(['is_boiler_supply' => true]);

        foreach (self::BILLS as [$type, $invoice, $month, $consumption, $amount]) {
            UtilityBill::updateOrCreate(
                ['type' => UtilityType::from($type), 'invoice_number' => $invoice],
                [
                    'month' => $month,
                    'consumption_value' => $consumption,
                    'net_amount' => $amount,
                    'net_price' => round($amount / $consumption, 4),
                ],
            );
        }

        $this->command?->info(sprintf(
            'Zaimportowano liczniki ciepła i wody dla 2 lokali, %d odczytów i %d rachunków.',
            Reading::where('source_table', Reading::SOURCE_MANUAL)->count(),
            count(self::BILLS),
        ));
    }

    private function unitByElectricMeter(string $serial): Unit
    {
        $meter = Meter::where('type', MeterType::Electric)->where('serial_number', $serial)->first();
        $unit = $meter?->currentUnit();

        if ($unit === null) {
            throw new RuntimeException("Nie znaleziono lokalu z przypisanym licznikiem prądu {$serial}.");
        }

        return $unit;
    }

    private function meter(MeterType $type, array $spec): Meter
    {
        $meter = Meter::firstOrCreate(
            ['type' => $type, 'serial_number' => $spec['serial']],
            ['name' => $spec['name'], 'model' => $spec['model'], 'is_active' => true, 'is_main' => false],
        );

        if (blank($meter->model)) {
            $meter->update(['model' => $spec['model']]);
        }

        return $meter;
    }

    private function reading(Meter $meter, string $date, float $value): void
    {
        $reading = Reading::query()
            ->where('meter_id', $meter->id)
            ->where('source_table', Reading::SOURCE_MANUAL)
            ->whereDate('reading_date', $date)
            ->first() ?? new Reading([
                'meter_id' => $meter->id,
                'source_table' => Reading::SOURCE_MANUAL,
                'reading_date' => $date,
            ]);

        $reading->fill([
            'source_meter_serial' => $meter->serial_number,
            'consumption' => $value,
        ])->save();
    }
}
