<?php

namespace App\Enums;

enum UtilityType: string
{
    case Electric = 'electric';
    case Water = 'water';

    public function label(): string
    {
        return match ($this) {
            self::Electric => 'Prąd',
            self::Water => 'Woda',
        };
    }

    public function meterType(): MeterType
    {
        return match ($this) {
            self::Electric => MeterType::Electric,
            self::Water => MeterType::Water,
        };
    }
}
