<?php

namespace App\Services;

use App\Models\RentCharge;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Zestawienie płatności czynszu najemcy — wspólne dla karty najemcy, rocznego PDF
 * i przypomnienia e-mail, żeby wszystkie trzy pokazywały te same liczby.
 */
class RentPaymentSummary
{
    /**
     * @return array<string, mixed>
     */
    public function forTenant(Tenant $tenant, int $year): array
    {
        $charges = $this->charges($tenant)->filter(
            fn (RentCharge $charge) => (int) $charge->month->year === $year,
        )->values();

        $charged = $charges->sum(fn (RentCharge $charge) => (float) $charge->amount);
        $paid = $charges->filter->isPaid()->sum(fn (RentCharge $charge) => (float) $charge->amount);

        return [
            'year' => $year,
            'years' => $this->years($tenant, $year),
            'charges' => $charges,
            'byUnit' => $charges->groupBy('unit_id'),
            'charged' => $charged,
            'paid' => $paid,
            'outstanding' => round($charged - $paid, 2),
            'overdue' => $charges->filter->isOverdue()->values(),
            'paidLate' => $charges->filter(
                fn (RentCharge $charge) => $charge->isPaid() && $charge->due_on && $charge->paid_on->gt($charge->due_on),
            )->values(),
        ];
    }

    /** Wszystkie niezapłacone czynsze po terminie — niezależnie od rocznika. */
    public function outstandingFor(Tenant $tenant): Collection
    {
        return $this->charges($tenant)->filter->isOverdue()->values();
    }

    public function outstandingTotalFor(Tenant $tenant): float
    {
        return round($this->outstandingFor($tenant)->sum(fn (RentCharge $charge) => (float) $charge->amount), 2);
    }

    private function charges(Tenant $tenant): Collection
    {
        return $tenant->rentCharges()
            ->with('unit')
            ->orderBy('month')
            ->orderBy('unit_id')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    private function years(Tenant $tenant, int $selected): array
    {
        // toBase(): pusta kolekcja Eloquent zostaje kolekcją modeli i wywraca się
        // na unique(), gdy dopiszemy do niej zwykłe liczby (najemca bez naliczeń).
        return $this->charges($tenant)
            ->map(fn (RentCharge $charge) => (int) $charge->month->year)
            ->toBase()
            ->push($selected)
            ->push((int) now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
