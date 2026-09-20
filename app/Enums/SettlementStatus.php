<?php

namespace App\Enums;

enum SettlementStatus: string
{
    case Draft = 'draft';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Szkic',
            self::Final => 'Zatwierdzone',
        };
    }
}
