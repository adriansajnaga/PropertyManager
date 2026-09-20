<?php

namespace App\Http\Controllers;

use App\Http\Requests\RentChargeRequest;
use App\Models\RentCharge;
use App\Models\Unit;
use App\Services\RentAccrualService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class RentChargeController extends Controller
{
    public function __construct(private readonly RentAccrualService $accrual) {}

    public function index(Request $request)
    {
        // Wejście na listę uzupełnia brakujące miesiące, więc czynsz czeka gotowy
        // nawet wtedy, gdy harmonogram Laravela nie jest uruchomiony.
        $this->accrual->run();

        $year = (int) $request->query('year', now()->year);

        $charges = RentCharge::with('unit.property', 'tenant')
            ->forYear($year)
            ->orderByDesc('month')
            ->orderBy('unit_id')
            ->get();

        return view('rent-charges.index', [
            'year' => $year,
            'years' => $this->years($year),
            'chargesByMonth' => $charges->groupBy(fn (RentCharge $charge) => $charge->month->format('Y-m')),
            'chargedTotal' => $charges->sum(fn (RentCharge $charge) => (float) $charge->amount),
            'paidTotal' => $charges->filter->isPaid()->sum(fn (RentCharge $charge) => (float) $charge->amount),
            'overdueCount' => $charges->filter->isOverdue()->count(),
        ]);
    }

    public function create(Request $request)
    {
        $unit = $request->filled('unit_id') ? Unit::find($request->query('unit_id')) : null;

        return view('rent-charges.create', $this->formData(new RentCharge([
            'unit_id' => $unit?->id,
            'month' => $this->monthFromRequest($request, now()->format('Y-m')),
            'amount' => $unit?->rent_amount,
        ])));
    }

    public function store(RentChargeRequest $request)
    {
        $charge = RentCharge::create($this->attributes($request));

        return redirect()->route('rent-charges.index', ['year' => $charge->month->year])
            ->with('status', 'Czynsz został dodany.');
    }

    public function edit(RentCharge $rentCharge)
    {
        return view('rent-charges.edit', $this->formData($rentCharge));
    }

    public function update(RentChargeRequest $request, RentCharge $rentCharge)
    {
        $rentCharge->update($this->attributes($request));

        return redirect()->route('rent-charges.index', ['year' => $rentCharge->month->year])
            ->with('status', 'Czynsz został zaktualizowany.');
    }

    public function destroy(RentCharge $rentCharge)
    {
        $year = $rentCharge->month->year;
        $rentCharge->delete();

        return redirect()->route('rent-charges.index', ['year' => $year])
            ->with('status', 'Naliczenie czynszu zostało usunięte. Nie wróci przy kolejnym naliczaniu.');
    }

    /** Szybkie potwierdzenie zapłaty prosto z listy — bez wchodzenia w formularz. */
    public function togglePaid(Request $request, RentCharge $rentCharge)
    {
        $rentCharge->update([
            'paid_on' => $rentCharge->isPaid() ? null : ($request->input('paid_on') ?: now()->toDateString()),
        ]);

        return back()->with('status', $rentCharge->isPaid()
            ? 'Czynsz oznaczony jako zapłacony.'
            : 'Zapłata czynszu została cofnięta.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(RentChargeRequest $request): array
    {
        $data = $request->safe()->only(['unit_id', 'month', 'amount', 'invoice_number', 'due_on', 'note']);
        $data['paid_on'] = $request->validated('paid_on');
        $data['tenant_id'] = Unit::find($data['unit_id'])?->tenantAt($data['month'])?->id;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(RentCharge $charge): array
    {
        return [
            'charge' => $charge,
            'units' => Unit::with('property')->orderBy('property_id')->orderBy('description')->get(),
        ];
    }

    private function monthFromRequest(Request $request, string $default): CarbonImmutable
    {
        $value = (string) ($request->input('month') ?: $default);

        return CarbonImmutable::parse($value)->startOfMonth();
    }

    /**
     * @return array<int, int>
     */
    private function years(int $selected): array
    {
        $known = RentCharge::query()->pluck('month')->map(fn ($month) => (int) CarbonImmutable::parse($month)->year);

        return $known->push($selected)->push(now()->year)->unique()->sortDesc()->values()->all();
    }
}
