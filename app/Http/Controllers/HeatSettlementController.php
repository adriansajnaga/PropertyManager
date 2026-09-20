<?php

namespace App\Http\Controllers;

use App\Enums\UtilityType;
use App\Models\HeatSettlement;
use App\Models\UtilityBill;
use App\Services\HeatSettlementCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class HeatSettlementController extends Controller
{
    public function __construct(private readonly HeatSettlementCalculator $calculator) {}

    public function index()
    {
        return view('heat-settlements.index', [
            'settlements' => HeatSettlement::with('electricBill')
                ->orderByDesc('month')
                ->paginate(24),
        ]);
    }

    public function create(Request $request)
    {
        $month = CarbonImmutable::parse($request->query('month', now()->subMonth()->format('Y-m').'-01'))->startOfMonth();

        $bills = UtilityBill::where('type', UtilityType::Electric)
            ->orderByDesc('month')
            ->get();

        $billId = $request->query('electric_bill_id');
        $bill = $billId
            ? $bills->firstWhere('id', (int) $billId)
            : $bills->first(fn (UtilityBill $b) => $b->month->isSameMonth($month));

        return view('heat-settlements.create', [
            'month' => $month,
            'bills' => $bills,
            'selectedBill' => $bill,
            'preview' => $bill ? $this->calculator->preview($month, $bill) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'date'],
            'electric_bill_id' => ['required', 'exists:utility_bills,id'],
        ]);

        $settlement = $this->calculator->store(
            CarbonImmutable::parse($data['month'])->startOfMonth(),
            UtilityBill::findOrFail($data['electric_bill_id']),
        );

        return redirect()->route('heat-settlements.show', $settlement)
            ->with('status', 'Rozliczenie kosztów ciepła zostało zapisane.');
    }

    public function show(HeatSettlement $heatSettlement)
    {
        return view('heat-settlements.show', [
            'settlement' => $heatSettlement->load('lines.meter', 'electricBill'),
        ]);
    }

    public function destroy(HeatSettlement $heatSettlement)
    {
        $heatSettlement->delete();

        return redirect()->route('heat-settlements.index')
            ->with('status', 'Rozliczenie kosztów ciepła zostało usunięte.');
    }
}
