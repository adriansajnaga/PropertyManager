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

    public function unit(): string
    {
        return match ($this) {
            self::Electric => 'kWh',
            self::Heat => 'GJ',
            self::Water => 'm³',
        };
    }
}
