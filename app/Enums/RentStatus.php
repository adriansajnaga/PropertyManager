<?php

namespace App\Enums;

enum RentStatus: string
{
    /** Naliczony automatycznie, czeka na wystawienie faktury. */
    case Draft = 'draft';

    /** Faktura wystawiona — znamy numer i termin płatności. */
    case Issued = 'issued';

    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'szkic',
            self::Issued => 'wystawiony',
            self::Paid => 'zapłacony',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Issued => 'amber',
            self::Paid => 'green',
        };
    }
}
