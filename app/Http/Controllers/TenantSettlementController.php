<?php

namespace App\Http\Controllers;

use App\Enums\SettlementStatus;
use App\Enums\UtilityType;
use App\Models\HeatSettlement;
use App\Models\Property;
use App\Models\TenantSettlement;
use App\Models\Unit;
use App\Models\UtilityBill;
use App\Mail\TenantSettlementMail;
use App\Services\TenantSettlementCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use RuntimeException;

class TenantSettlementController extends Controller
{
    public function __construct(private readonly TenantSettlementCalculator $calculator) {}

    public function index(Request $request)
    {
        $month = $this->monthFromRequest($request, now()->format('Y-m'));

        $settlements = TenantSettlement::query()
            ->whereDate('month', $month->toDateString())
            ->get()
            ->keyBy('unit_id');

        return view('tenant-settlements.index', [
            'month' => $month,
            'previousMonth' => $month->subMonth(),
            'nextMonth' => $month->addMonth(),
            'properties' => Property::with(['units' => fn ($q) => $q->orderBy('description')])
                ->orderBy('name')
                ->get(),
            'settlements' => $settlements,
        ]);
    }

    public function create(Request $request)
    {
        $unit = Unit::with('property')->findOrFail($request->integer('unit_id'));
        $month = $this->monthFromRequest($request, now()->subMonth()->format('Y-m'));

        if (! $unit->settles_utilities) {
            return redirect()->route('units.show', $unit)
                ->withErrors(['settlement' => 'Ten lokal rozlicza media samodzielnie — rozliczenia mu nie tworzymy.']);
        }

        $existing = TenantSettlement::query()
            ->where('unit_id', $unit->id)
            ->whereDate('month', $month->toDateString())
            ->first();

        if ($existing?->isFinal()) {
            return redirect()->route('tenant-settlements.show', $existing)
                ->withErrors(['settlement' => 'Rozliczenie jest zatwierdzone i nie może być zmieniane.']);
        }

        // Bez wyboru w formularzu otwieramy szkic z rachunkami, które zapisano w nim wcześniej.
        $billIds = $request->hasAny(['water_bill_id', 'electric_bill_id'])
            ? $this->billIdsFromRequest($request)
            : ($existing?->billIds() ?? []);

        return view('tenant-settlements.create', [
            'unit' => $unit,
            'month' => $month,
            'existing' => $existing,
            'preview' => $this->calculator->preview($unit, $month, $billIds),
            'waterBills' => UtilityBill::where('type', UtilityType::Water)->orderByDesc('month')->orderByDesc('id')->get(),
            'electricBills' => UtilityBill::where('type', UtilityType::Electric)->orderByDesc('month')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'month' => ['required', 'date'],
            'water_bill_id' => ['nullable', Rule::exists('utility_bills', 'id')->where('type', UtilityType::Water->value)],
            'electric_bill_id' => ['nullable', Rule::exists('utility_bills', 'id')->where('type', UtilityType::Electric->value)],
        ], [
            'water_bill_id.exists' => 'Wybrany rachunek nie jest rachunkiem za wodę.',
            'electric_bill_id.exists' => 'Wybrany rachunek nie jest rachunkiem za prąd.',
        ]);

        $unit = Unit::findOrFail($data['unit_id']);
        $month = CarbonImmutable::parse($data['month'])->startOfMonth();

        if (! $unit->settles_utilities) {
            return back()->withErrors(['settlement' => 'Ten lokal rozlicza media samodzielnie — rozliczenia mu nie tworzymy.']);
        }

        try {
            $settlement = $this->calculator->store($unit, $month, $this->billIdsFromRequest($request));
        } catch (RuntimeException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return redirect()->route('tenant-settlements.show', $settlement)
            ->with('status', 'Rozliczenie zostało utworzone.');
    }

    public function show(TenantSettlement $tenantSettlement)
    {
        return view('tenant-settlements.show', [
            'settlement' => $tenantSettlement->load('lines', 'unit.property', 'tenant'),
        ]);
    }

    public function recalculate(TenantSettlement $tenantSettlement)
    {
        try {
            $this->calculator->store(
                $tenantSettlement->unit,
                CarbonImmutable::parse($tenantSettlement->month)->startOfMonth(),
                $tenantSettlement->billIds(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return redirect()->route('tenant-settlements.show', $tenantSettlement)
            ->with('status', 'Rozliczenie zostało przeliczone ponownie.');
    }

    public function finalize(TenantSettlement $tenantSettlement)
    {
        $tenantSettlement->update(['status' => SettlementStatus::Final]);

        return back()->with('status', 'Rozliczenie zostało zatwierdzone — dalsze zmiany są zablokowane.');
    }

    public function pdf(TenantSettlement $tenantSettlement)
    {
        return $this->pdfFor($tenantSettlement)->download($this->pdfFilename($tenantSettlement));
    }

    public function pdfFor(TenantSettlement $settlement)
    {
        $settlement->load('lines', 'unit.property', 'tenant', 'waterBill', 'electricBill');

        return Pdf::loadView('pdf.tenant-settlement', [
            'settlement' => $settlement,
            'heatSettlement' => HeatSettlement::with('lines')
                ->whereDate('month', $settlement->month->toDateString())
                ->first(),
            'maintenanceCosts' => $settlement->unit->property
                ->maintenanceCostsForYear((int) $settlement->month->year)
                ->orderBy('description')
                ->get(),
        ])->setPaper('a4');
    }

    public function pdfFilename(TenantSettlement $settlement): string
    {
        return sprintf(
            'rozliczenie-%s-%s.pdf',
            $settlement->month->format('Y-m'),
            str($settlement->unit->description)->slug(),
        );
    }

    public function email(Request $request, TenantSettlement $tenantSettlement)
    {
        if (! $tenantSettlement->isFinal()) {
            return back()->withErrors(['email' => 'Rozliczenie trzeba najpierw zatwierdzić.']);
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ], [], [
            'subject' => 'temat',
            'body' => 'treść wiadomości',
        ]);

        Mail::to($data['email'])->send(new TenantSettlementMail(
            $tenantSettlement,
            $this->pdfFor($tenantSettlement)->output(),
            $this->pdfFilename($tenantSettlement),
            $data['body'],
            $data['subject'],
        ));

        return back()->with('status', "Rozliczenie zostało wysłane na adres {$data['email']}.");
    }

    public function destroy(TenantSettlement $tenantSettlement)
    {
        if ($tenantSettlement->isFinal()) {
            return back()->withErrors(['settlement' => 'Zatwierdzonego rozliczenia nie można usunąć.']);
        }

        $month = $tenantSettlement->month->format('Y-m');
        $tenantSettlement->delete();

        return redirect()->route('tenant-settlements.index', ['month' => $month])
            ->with('status', 'Rozliczenie zostało usunięte.');
    }

    /**
     * @return array<string, int|null>
     */
    private function billIdsFromRequest(Request $request): array
    {
        return [
            'water' => $request->filled('water_bill_id') ? $request->integer('water_bill_id') : null,
            'electric' => $request->filled('electric_bill_id') ? $request->integer('electric_bill_id') : null,
        ];
    }

    private function monthFromRequest(Request $request, string $default): CarbonImmutable
    {
        $value = (string) $request->query('month', $default);

        return CarbonImmutable::parse(strlen($value) === 7 ? $value.'-01' : $value)->startOfMonth();
    }
}
