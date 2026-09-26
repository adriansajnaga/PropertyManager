<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    /** Wystawiona w aplikacji, jeszcze nie wysłana do KSeF. */
    case Draft = 'draft';

    /** Wysłana z aplikacji i przyjęta przez KSeF. */
    case Sent = 'sent';

    /** Wystawiona poza aplikacją, pobrana z KSeF. */
    case Imported = 'imported';

    /** KSeF odrzucił dokument. */
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'wystawiona, niewysłana',
            self::Sent => 'wysłana do KSeF',
            self::Imported => 'pobrana z KSeF',
            self::Rejected => 'odrzucona przez KSeF',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Sent => 'green',
            self::Imported => 'sky',
            self::Rejected => 'red',
        };
    }
}
