<?php

namespace App\Support;

class Format
{
    /** Dokładność wynika z klasy licznika: ciepło 0,0001 GJ, woda 0,001 m³, prąd 0,1 kWh. */
    private const DECIMALS = [
        'GJ' => 4,
        'm³' => 3,
        'kWh' => 1,
        'm²' => 2,
    ];

    public static function decimals(?string $unit): int
    {
        return self::DECIMALS[$unit] ?? 2;
    }

    /** Stan licznika albo zużycie, w dokładności właściwej dla medium. */
    public static function reading(float|string|null $value, ?string $unit = null, bool $withUnit = false): string
    {
        if ($value === null) {
            return '—';
        }

        $formatted = self::number($value, self::decimals($unit));

        return $withUnit && $unit ? $formatted.' '.$unit : $formatted;
    }

    /**
     * Cena jednostkowa: za GJ liczymy i pokazujemy w groszach, ceny prądu i wody
     * pochodzą wprost z faktur i bywają czterocyfrowe po przecinku.
     */
    public static function price(float|string|null $value, ?string $unit = null): string
    {
        return self::number($value ?? 0, $unit === 'GJ' ? 2 : 4);
    }

    public static function money(float|string|null $value, int $decimals = 2): string
    {
        return self::number($value ?? 0, $decimals);
    }

    private static function number(float|string $value, int $decimals): string
    {
        return number_format((float) $value, $decimals, ',', ' ');
    }
}
