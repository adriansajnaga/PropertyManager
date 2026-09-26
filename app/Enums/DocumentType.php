<?php

namespace App\Enums;

enum DocumentType: string
{
    /** Faktura VAT — trafia do KSeF. */
    case Invoice = 'invoice';

    /** Rachunek imienny — dokument bez VAT, wystawiany przy zawieszonej działalności. */
    case Receipt = 'receipt';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'faktura',
            self::Receipt => 'rachunek',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Invoice => 'Faktura',
            self::Receipt => 'Rachunek',
        };
    }

    public function goesToKsef(): bool
    {
        return $this === self::Invoice;
    }
}
