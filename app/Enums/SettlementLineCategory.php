<?php

namespace App\Enums;

enum SettlementLineCategory: string
{
    case Water = 'water';
    case Electric = 'electric';
    case Heat = 'heat';
    case MaintenanceCost = 'maintenance_cost';

    public function label(): string
    {
        return match ($this) {
            self::Water => 'Woda',
            self::Electric => 'Prąd',
            self::Heat => 'Ciepło',
            self::MaintenanceCost => 'Koszt utrzymania',
        };
    }

    public function meterType(): ?MeterType
    {
        return match ($this) {
            self::Water => MeterType::Water,
            self::Electric => MeterType::Electric,
            self::Heat => MeterType::Heat,
            self::MaintenanceCost => null,
        };
    }
}
