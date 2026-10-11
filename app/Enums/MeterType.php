<?php

namespace App\Enums;

enum MeterType: string
{
    case Electric = 'electric';
    case Heat = 'heat';
    case Water = 'water';

    public function label(): string
    {
        return match ($this) {
            self::Electric => 'Prąd',
            self::Heat => 'Ciepło',
            self::Water => 'Woda',
        };
    }

    /** Barwa medium — ta sama na pulpicie, listach i znacznikach odczytów. */
    public function color(): string
    {
        return match ($this) {
            self::Electric => 'amber',
            self::Heat => 'rose',
            self::Water => 'sky',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Electric => 'kWh',
            self::Heat => 'GJ',
            self::Water => 'm³',
        };
    }
}
