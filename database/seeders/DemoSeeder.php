<?php

namespace Database\Seeders;

use App\Enums\MeterType;
use App\Enums\UtilityType;
use App\Models\MaintenanceCost;
use App\Models\Meter;
use App\Models\Property;
use App\Models\Reading;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UtilityBill;
use Illuminate\Database\Seeder;

/**
 * Dane demonstracyjne: Budynek A za luty 2026 — cena ciepła wychodzi 4,25 zł/GJ,
 * a rozliczenie Lokalu 12 zamyka się kwotą 475,00 zł netto (584,25 zł brutto).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::create([
            'name' => 'Budynek A',
            'description' => 'Budynek biurowo-magazynowy z własną kotłownią.',
            'address' => 'ul. Piaskowa 27, 22-400 Zamość',
            'total_area' => 1000,
        ]);

        MaintenanceCost::insert([
            ['property_id' => $property->id, 'description' => 'Sprzątanie i utrzymanie terenu', 'annual_cost' => 14400, 'year' => 2026, 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $property->id, 'description' => 'Ubezpieczenie i podatek od nieruchomości', 'annual_cost' => 9600, 'year' => 2026, 'created_at' => now(), 'updated_at' => now()],
        ]);

        UtilityBill::create([
            'type' => UtilityType::Water,
            'invoice_number' => 'FV/W/02/2026',
            'net_price' => 8.00,
            'consumption_value' => 210,
            'month' => '2026-02-01',
        ]);

        $electricBill = UtilityBill::create([
            'type' => UtilityType::Electric,
            'invoice_number' => 'FV/E/02/2026',
            'net_price' => 0.85,
            'consumption_value' => 4700,
            'month' => '2026-02-01',
        ]);

        // Kotłownia ma własny podlicznik prądu — jego zużycie wycenione ceną z rachunku
        // daje koszt ciepła rozdzielany potem po GJ.
        $boiler = Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => 'E-KOTLOWNIA',
            'name' => config('pm.boiler_meter_name'),
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->readings($boiler, 10000, 10500);

        // Lokal 12 to przykład z dokumentacji, pozostałe lokale dopełniają
        // sumę zużycia ciepła w budynku do 100 GJ.
        $blueprint = [
            ['Lokal 12', 50, ['water' => [120, 135], 'electric' => [1000, 1200], 'heat' => [300, 320]], 'Sajnaga Logistics sp. z o.o.'],
            ['Lokal 14', 80, ['water' => [80, 92], 'electric' => [2000, 2350], 'heat' => [500, 530]], 'Biuro Rachunkowe Kalkulus'],
            ['Lokal 16', 120, ['water' => [200, 226], 'electric' => [3000, 3480], 'heat' => [700, 730]], 'Hurtownia Elektryczna Volt'],
            ['Lokal 18', 60, ['water' => [50, 57], 'electric' => [900, 1080], 'heat' => [150, 170]], null],
        ];

        foreach ($blueprint as $index => [$description, $area, $meterReadings, $tenantName]) {
            $unit = Unit::create([
                'property_id' => $property->id,
                'description' => $description,
                'area' => $area,
            ]);

            if ($tenantName !== null) {
                $tenant = Tenant::create([
                    'name' => $tenantName,
                    'nip' => '922-11-2'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'city' => 'Zamość',
                    'zip' => '22-400',
                    'street' => 'ul. Piaskowa 27/'.$description,
                    'is_active' => true,
                ]);

                $unit->tenantAssignments()->create([
                    'tenant_id' => $tenant->id,
                    'valid_from' => '2026-01-01',
                ]);
            }

            foreach ($meterReadings as $type => [$start, $end]) {
                $meterType = MeterType::from($type);

                $meter = Meter::create([
                    'type' => $meterType,
                    'serial_number' => strtoupper($type[0]).'-'.str_replace(' ', '', $description),
                    'name' => $description.' — '.$meterType->label(),
                    'is_active' => true,
                    'is_main' => false,
                ]);

                $unit->meterAssignments()->create([
                    'meter_id' => $meter->id,
                    'valid_from' => '2026-01-01',
                ]);

                $this->readings($meter, $start, $end);
            }
        }

        $this->command?->info('Dane demo utworzone. Rachunek za prąd: '.$electricBill->invoice_number);
    }

    private function readings(Meter $meter, float $start, float $end): void
    {
        foreach ([['2026-02-01', $start], ['2026-03-01', $end]] as [$date, $value]) {
            Reading::create([
                'meter_id' => $meter->id,
                'source_table' => Reading::SOURCE_MANUAL,
                'consumption' => $value,
                'reading_date' => $date,
                'reading_timestamp' => $date.' 00:05:00',
            ]);
        }
    }
}
