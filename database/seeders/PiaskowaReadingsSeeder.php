<?php

namespace Database\Seeders;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\Reading;
use Illuminate\Database\Seeder;

/**
 * Rzeczywiste odczyty liczników prądu z Piaskowej 27 (portal odczytowy),
 * z pierwszego dnia miesiąca od stycznia do lipca 2026.
 *
 * Brakuje odczytu CB00703 na 01.04.2026 — nie ma go w źródle (najbliższy to 05.04).
 * Aplikacja przyjmie wtedy ostatni wcześniejszy odczyt, czyli ten z 01.03.
 */
class PiaskowaReadingsSeeder extends Seeder
{
    private const SOURCE_TABLE = 'modbus_electric_readings';

    private const MODEL = 'F&F LE-03M';

    /** serial => [nazwa licznika, czy to podlicznik kotłowni] */
    private const METERS = [
        'CB00703' => ['Piaskowa 27 — Góra', false],
        'CB00836' => ['Piaskowa 27 — Dół Duży', false],
        'CB01744' => ['Piaskowa 27 — Dół Mały', false],
        'CB01756' => [null, true],
    ];

    /** [source_id, serial, nazwa źródłowa, hex, kWh, data, timestamp] */
    private const READINGS = [
        [626, 'CB01756', 'Piaskowa27_Kotłownia', '02 03 06 00 00 00 2B 00 A5 85 F6', 1117.3, '2026-01-01', '2026-01-01 05:59:22'],
        [627, 'CB01744', 'Piaskowa27_Dół_Mały', '03 03 06 00 00 00 04 00 CD B8 41', 122.9, '2026-01-01', '2026-01-01 05:59:26'],
        [628, 'CB00836', 'Piaskowa27_Dół_Duży', '04 03 06 00 00 00 06 00 8F BF 80', 167.9, '2026-01-01', '2026-01-01 05:59:32'],
        [629, 'CB00703', 'Piaskowa27_Góra', '05 03 06 00 00 00 0F 00 7C 22 57', 396.4, '2026-01-01', '2026-01-01 05:59:37'],

        [750, 'CB01756', 'Piaskowa27_Kotłownia', '02 03 06 00 00 00 40 00 E8 34 1F', 1661.6, '2026-02-01', '2026-02-01 05:59:04'],
        [751, 'CB01744', 'Piaskowa27_Dół_Mały', '03 03 06 00 00 00 05 00 B1 E8 60', 145.7, '2026-02-01', '2026-02-01 05:59:09'],
        [752, 'CB00836', 'Piaskowa27_Dół_Duży', '04 03 06 00 00 00 07 00 91 6E 48', 193.7, '2026-02-01', '2026-02-01 05:59:14'],
        [753, 'CB00703', 'Piaskowa27_Góra', '05 03 06 00 00 00 10 00 A9 D2 0E', 426.5, '2026-02-01', '2026-02-01 05:59:19'],

        [852, 'CB01756', 'Piaskowa27_Kotłownia', '02 03 06 00 00 00 59 00 76 64 70', 2290.2, '2026-03-01', '2026-03-01 05:58:47'],
        [853, 'CB01744', 'Piaskowa27_Dół_Mały', '03 03 06 00 00 00 06 00 51 19 E8', 161.7, '2026-03-01', '2026-03-01 05:58:51'],
        [854, 'CB00836', 'Piaskowa27_Dół_Duży', '04 03 06 00 00 00 08 00 68 9E 09', 215.2, '2026-03-01', '2026-03-01 05:58:56'],
        [855, 'CB00703', 'Piaskowa27_Góra', '05 03 06 00 00 00 11 00 1D 83 B9', 438.1, '2026-03-01', '2026-03-01 05:59:01'],

        [965, 'CB01756', 'Piaskowa27_Kotłownia', '02 03 06 00 00 00 60 00 51 F4 67', 2465.7, '2026-04-01', '2026-04-01 05:58:33'],
        [966, 'CB01744', 'Piaskowa27_Dół_Mały', '03 03 06 00 00 00 07 00 19 48 1E', 181.7, '2026-04-01', '2026-04-01 05:58:38'],
        [967, 'CB00836', 'Piaskowa27_Dół_Duży', '04 03 06 00 00 00 09 00 82 4E 46', 243.4, '2026-04-01', '2026-04-01 05:58:43'],

        [1077, 'CB01756', 'Piaskowa27_Kotłownia', '02 03 06 00 00 00 66 00 48 D5 AC', 2618.4, '2026-05-01', '2026-05-01 05:58:10'],
        [1078, 'CB01744', 'Piaskowa27_Dół_Mały', '03 03 06 00 00 00 07 00 F5 49 93', 203.7, '2026-05-01', '2026-05-01 05:58:15'],
        [1079, 'CB00836', 'Piaskowa27_Dół_Duży', '04 03 06 00 00 00 0A 00 C9 FE 71', 276.1, '2026-05-01', '2026-05-01 05:58:20'],
        [1080, 'CB00703', 'Piaskowa27_Góra', '05 03 06 00 00 00 11 00 4D 83 85', 442.9, '2026-05-01', '2026-05-01 05:58:25'],

        [1177, 'CB01756', 'Piaskowa27_Kotłownia', '02 03 06 00 00 00 68 00 74 B4 7E', 2674.0, '2026-06-01', '2026-06-01 05:57:52'],
        [1178, 'CB01744', 'Piaskowa27_Dół_Mały', '03 03 06 00 00 00 08 00 B1 79 A3', 222.5, '2026-06-01', '2026-06-01 05:57:57'],
        [1179, 'CB00836', 'Piaskowa27_Dół_Duży', '04 03 06 00 00 00 0B 00 88 6F 81', 295.2, '2026-06-01', '2026-06-01 05:58:03'],
        [1180, 'CB00703', 'Piaskowa27_Góra', '05 03 06 00 00 00 11 00 79 82 52', 447.3, '2026-06-01', '2026-06-01 05:58:07'],

        [1286, 'CB01756', 'Piaskowa27_Kotłownia', '02 03 06 00 00 00 68 00 F9 74 1B', 2687.3, '2026-07-01', '2026-07-01 05:57:37'],
        [1287, 'CB01744', 'Piaskowa27_Dół_Mały', '03 03 06 00 00 00 09 00 C3 A8 46', 249.9, '2026-07-01', '2026-07-01 05:57:42'],
        [1288, 'CB00836', 'Piaskowa27_Dół_Duży', '04 03 06 00 00 00 0C 00 0B 9F E1', 308.3, '2026-07-01', '2026-07-01 05:57:47'],
        [1289, 'CB00703', 'Piaskowa27_Góra', '05 03 06 00 00 00 11 00 8F 02 14', 449.5, '2026-07-01', '2026-07-01 05:57:52'],
    ];

    public function run(): void
    {
        $meters = collect(self::METERS)->mapWithKeys(function (array $meter, string $serial) {
            [$name, $isBoiler] = $meter;

            $record = Meter::firstOrCreate(
                ['type' => MeterType::Electric, 'serial_number' => $serial],
                [
                    // Podlicznik kotłowni musi nazywać się dokładnie tak, jak w config/pm.php,
                    // bo po tej nazwie rozliczenie kosztów ciepła go odnajduje.
                    'name' => $isBoiler ? config('pm.boiler_meter_name') : $name,
                    'model' => self::MODEL,
                    'is_active' => true,
                    'is_main' => false,
                    'is_boiler_supply' => $isBoiler,
                ],
            );

            if ($isBoiler && ! $record->is_boiler_supply) {
                $record->update(['is_boiler_supply' => true]);
            }

            // Licznikowi dodanemu wcześniej ręcznie uzupełniamy tylko brakujący model.
            if (blank($record->model)) {
                $record->update(['model' => self::MODEL]);
            }

            return [$serial => $record];
        });

        foreach (self::READINGS as [$sourceId, $serial, $sourceName, $hex, $kwh, $date, $timestamp]) {
            Reading::updateOrCreate(
                ['source_table' => self::SOURCE_TABLE, 'source_id' => $sourceId],
                [
                    'meter_id' => $meters[$serial]->id,
                    'source_meter_serial' => $serial,
                    'source_meter_name' => $sourceName,
                    'raw_hex' => $hex,
                    'consumption' => $kwh,
                    'reading_date' => $date,
                    'reading_timestamp' => $timestamp,
                    'synced_at' => now(),
                ],
            );
        }

        $this->command?->info(sprintf(
            'Wczytano %d odczytów dla %d liczników (01.2026–07.2026).',
            count(self::READINGS),
            $meters->count(),
        ));
    }
}
