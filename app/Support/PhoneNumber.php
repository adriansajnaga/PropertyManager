<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Sprowadza numer wpisany w dowolnej formie („600 100 200", „+48 600-100-200")
     * do cyfr z prefiksem kraju. Dziewięć cyfr traktujemy jak numer polski.
     */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 9) {
            $digits = '48'.$digits;
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }

    /** Numer do pokazania człowiekowi: +48 600 100 200. */
    public static function format(?string $phone): string
    {
        $digits = self::normalize($phone);

        if ($digits === null) {
            return (string) $phone;
        }

        if (str_starts_with($digits, '48') && strlen($digits) === 11) {
            return '+48 '.implode(' ', str_split(substr($digits, 2), 3));
        }

        return '+'.$digits;
    }
}
