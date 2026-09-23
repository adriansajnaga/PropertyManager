<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Reading;
use Illuminate\Support\Facades\Log;

/**
 * Nakładka radiowa liczy od stanu zastanego u poprzedniego użytkownika, więc jej
 * odczyt nie jest stanem wodomierza. Z każdego odczytu nakładki robimy drugi wpis —
 * już na koncie licznika mechanicznego — powiększony o różnicę wskazań.
 *
 * Odczyt nakładki zostaje w bazie nietknięty: widać, skąd wzięła się wartość,
 * a po poprawieniu różnicy przeliczone odczyty aktualizują się same.
 */
class ModuleReadingConverter
{
    /** Przelicza odczyty wszystkich nakładek; zwraca liczbę zapisanych odczytów licznika. */
    public function convertAll(): int
    {
        return Meter::query()
            ->where('is_module', true)
            ->whereNotNull('module_for_meter_id')
            ->with('moduleFor')
            ->get()
            ->sum(fn (Meter $module) => $this->convertFor($module));
    }

    public function convertFor(Meter $module): int
    {
        $meter = $module->moduleFor;

        if (! $module->is_module || $meter === null) {
            return 0;
        }

        $offset = (float) $module->module_offset;
        $converted = 0;

        foreach ($module->readings()->orderBy('reading_date')->get() as $raw) {
            $state = round((float) $raw->consumption + $offset, 4);

            // Ujemny stan licznika to na pewno zła różnica wskazań — nie zapisujemy bzdury.
            if ($state < 0) {
                Log::warning('Odczyt nakładki po przeliczeniu wychodzi ujemny — sprawdź różnicę wskazań.', [
                    'module' => $module->serial_number,
                    'reading_id' => $raw->id,
                    'state' => $state,
                ]);

                continue;
            }

            // Klucz (source_table, source_id) jest unikalny, więc ponowne przeliczenie
            // aktualizuje istniejący wpis zamiast dokładać kolejny.
            Reading::updateOrCreate(
                ['source_table' => Reading::SOURCE_MODULE, 'source_id' => $raw->id],
                [
                    'meter_id' => $meter->id,
                    'consumption' => $state,
                    'reading_date' => $raw->reading_date,
                    'reading_timestamp' => $raw->reading_timestamp,
                    'source_meter_serial' => $meter->serial_number,
                    'source_meter_name' => 'nakładka '.$module->serial_number,
                    'raw_hex' => $raw->raw_hex,
                ],
            );

            $converted++;
        }

        return $converted;
    }
}
