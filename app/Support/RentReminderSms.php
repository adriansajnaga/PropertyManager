<?php

namespace App\Support;

use App\Models\RentCharge;
use Illuminate\Support\Collection;

class RentReminderSms
{
    /**
     * Krótka treść przypomnienia — przy jednym zaległym miesiącu mieści się w jednym SMS-ie.
     *
     * @param  Collection<int, RentCharge>  $charges
     */
    public static function defaultText(Collection $charges): string
    {
        $months = $charges
            ->map(fn (RentCharge $charge) => $charge->month->isoFormat('MMMM YYYY'))
            ->unique()
            ->implode(', ');

        $total = Format::money($charges->sum(fn (RentCharge $charge) => (float) $charge->amount));

        return sprintf(
            'Przypomnienie: brak wpłaty czynszu za %s, kwota %s zł. Jeśli wpłata została już wykonana, prosimy zignorować. %s',
            $months,
            $total,
            config('pm.brand_owner'),
        );
    }
}
