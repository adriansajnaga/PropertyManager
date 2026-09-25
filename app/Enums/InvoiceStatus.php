<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    /** Wystawiona w aplikacji, jeszcze nie wysłana do KSeF. */
    case Draft = 'draft';

    /** Przyjęta przez KSeF — ma nadany numer KSeF. */
    case Sent = 'sent';

    /** KSeF odrzucił dokument. */
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'wystawiona',
            self::Sent => 'w KSeF',
            self::Rejected => 'odrzucona',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Sent => 'green',
            self::Rejected => 'red',
        };
    }
}
