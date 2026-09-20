<?php

namespace App\Services;

use App\Models\RentCharge;
use App\Models\Unit;
use Carbon\CarbonImmutable;

/**
 * Czynsz nalicza się sam: każdy wynajęty lokal ze stawką w kartotece dostaje
 * szkic czynszu za kolejne miesiące, gotowy do wystawienia faktury i zapłaty.
 */
class RentAccrualService
{
    /** Ile miesięcy wstecz wolno uzupełnić przy pierwszym naliczeniu lokalu. */
    private const MAX_MONTHS_BACK = 12;

    /**
     * Uzupełnia naliczenia do wskazanego miesiąca włącznie i zwraca liczbę nowych wpisów.
     */
    public function run(?CarbonImmutable $through = null): int
    {
        $through = ($through ?? CarbonImmutable::now())->startOfMonth();
        $floor = $through->subMonths(self::MAX_MONTHS_BACK);
        $created = 0;

        $units = Unit::query()
            ->whereNotNull('rent_amount')
            ->where('rent_amount', '>', 0)
            ->get();

        foreach ($units as $unit) {
            $created += $this->accrueUnit($unit, $through, $floor);
        }

        return $created;
    }

    private function accrueUnit(Unit $unit, CarbonImmutable $through, CarbonImmutable $floor): int
    {
        $start = $this->startMonth($unit, $through);

        if ($start->lt($floor)) {
            $start = $floor;
        }

        $created = 0;
        $lastCharged = null;

        for ($month = $start; $month->lte($through); $month = $month->addMonth()) {
            $tenant = $unit->tenantAt($month->toDateString());

            // Pusty lokal nie generuje czynszu.
            if ($tenant === null) {
                continue;
            }

            $exists = $unit->rentCharges()->whereDate('month', $month->toDateString())->exists();

            if (! $exists) {
                RentCharge::create([
                    'unit_id' => $unit->id,
                    'tenant_id' => $tenant->id,
                    'month' => $month->toDateString(),
                    'amount' => $unit->rent_amount,
                ]);

                $created++;
            }

            $lastCharged = $month;
        }

        $this->rememberProgress($unit, $lastCharged);

        return $created;
    }

    /**
     * Znacznik pilnuje tylko tego, żeby skasowany czynsz nie wrócił, więc przesuwamy go
     * wyłącznie za miesiące faktycznie naliczone. Miesiąc pominięty dlatego, że lokal nie
     * miał jeszcze najemcy albo stawki, zostaje otwarty i naliczy się, gdy dane się pojawią.
     */
    private function rememberProgress(Unit $unit, ?CarbonImmutable $lastCharged): void
    {
        if ($lastCharged === null) {
            return;
        }

        $current = $unit->rent_accrued_through;

        if ($current !== null && ! $lastCharged->isAfter(CarbonImmutable::parse($current))) {
            return;
        }

        $unit->forceFill(['rent_accrued_through' => $lastCharged->toDateString()])->save();
    }

    /**
     * Pierwszy miesiąc do naliczenia: kolejny po ostatnio naliczonym, a dla lokalu
     * jeszcze nienaliczanego — miesiąc rozpoczęcia najmu.
     */
    private function startMonth(Unit $unit, CarbonImmutable $through): CarbonImmutable
    {
        if ($unit->rent_accrued_through) {
            return CarbonImmutable::parse($unit->rent_accrued_through)->startOfMonth()->addMonth();
        }

        $firstTenancy = $unit->tenantAssignments()->min('valid_from');

        return $firstTenancy ? CarbonImmutable::parse($firstTenancy)->startOfMonth() : $through;
    }
}
