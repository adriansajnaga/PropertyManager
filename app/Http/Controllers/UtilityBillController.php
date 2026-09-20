<?php

namespace App\Http\Controllers;

use App\Enums\SettlementLineCategory;
use App\Enums\UtilityType;
use App\Http\Requests\UtilityBillRequest;
use App\Models\HeatSettlement;
use App\Models\TenantSettlement;
use App\Models\UtilityBill;

class UtilityBillController extends Controller
{
    public function index()
    {
        return view('utility-bills.index', [
            'billsByMonth' => UtilityBill::orderByDesc('month')
                ->orderBy('type')
                ->get()
                ->groupBy(fn (UtilityBill $bill) => $bill->month->format('Y-m')),
        ]);
    }

    public function show(UtilityBill $utilityBill)
    {
        $isElectric = $utilityBill->type === UtilityType::Electric;
        $category = $isElectric ? SettlementLineCategory::Electric : SettlementLineCategory::Water;
        $column = $isElectric ? 'electric_bill_id' : 'water_bill_id';

        $settlements = TenantSettlement::query()
            ->with(['unit.property', 'tenant', 'lines' => fn ($q) => $q->where('category', $category)])
            ->where($column, $utilityBill->id)
            ->orderBy('month')
            ->get();

        // Prąd zużyty przez pompę ciepła nie trafia na rozliczenia lokali wprost —
        // rozchodzi się przez cenę za GJ, więc liczymy go osobno.
        $heatSettlements = $isElectric
            ? HeatSettlement::where('electric_bill_id', $utilityBill->id)->orderBy('month')->get()
            : collect();

        $settledByUnits = $settlements->sum(fn (TenantSettlement $s) => (float) $s->lines->sum('consumption'));
        $settledByBoiler = (float) $heatSettlements->sum('boiler_kwh_consumed');

        return view('utility-bills.show', [
            'bill' => $utilityBill,
            'settlements' => $settlements,
            'heatSettlements' => $heatSettlements,
            'settledByUnits' => $settledByUnits,
            'settledByBoiler' => $settledByBoiler,
            'settledTotal' => $settledByUnits + $settledByBoiler,
            'unitLabel' => $isElectric ? 'kWh' : 'm³',
            'showCoverage' => $isElectric,
        ]);
    }

    public function create()
    {
        return view('utility-bills.create', [
            'bill' => new UtilityBill(['month' => now()->startOfMonth()]),
            'types' => UtilityType::cases(),
        ]);
    }

    public function store(UtilityBillRequest $request)
    {
        UtilityBill::create($request->validated());

        return redirect()->route('utility-bills.index')
            ->with('status', 'Rachunek został dodany.');
    }

    public function edit(UtilityBill $utilityBill)
    {
        return view('utility-bills.edit', [
            'bill' => $utilityBill,
            'types' => UtilityType::cases(),
        ]);
    }

    public function update(UtilityBillRequest $request, UtilityBill $utilityBill)
    {
        $utilityBill->update($request->validated());

        return redirect()->route('utility-bills.index')
            ->with('status', 'Rachunek został zaktualizowany.');
    }

    public function destroy(UtilityBill $utilityBill)
    {
        $utilityBill->delete();

        return redirect()->route('utility-bills.index')
            ->with('status', 'Rachunek został usunięty.');
    }
}
