<?php

namespace App\Http\Controllers;

use App\Enums\MeterType;
use App\Http\Requests\UnitRequest;
use App\Models\Meter;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
{
    private const ATTRIBUTES = [
        'property_id', 'description', 'area', 'rent_amount', 'deposit_amount', 'deposit_paid_on',
        'settles_utilities',
    ];

    public function index()
    {
        return view('units.index', [
            'properties' => Property::with(['units' => fn ($q) => $q->orderBy('description')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create()
    {
        return view('units.create', $this->formData(new Unit));
    }

    public function store(UnitRequest $request)
    {
        $unit = DB::transaction(function () use ($request) {
            $unit = Unit::create($request->safe()->only(self::ATTRIBUTES));
            $this->syncAssignments($unit, $request);

            return $unit;
        });

        return redirect()->route('units.show', $unit)
            ->with('status', 'Lokal został dodany.');
    }

    public function show(Unit $unit)
    {
        $unit->load('property');

        return view('units.show', [
            'unit' => $unit,
            'tenant' => $unit->currentTenant(),
            'meters' => $unit->currentMeters(),
            'settlements' => $unit->settlements()->latest('month')->limit(12)->get(),
            'rentCharges' => $unit->rentCharges()->orderByDesc('month')->limit(12)->get(),
            'documents' => $unit->documents()->with('uploader')->latest()->get(),
        ]);
    }

    public function edit(Unit $unit)
    {
        return view('units.edit', $this->formData($unit));
    }

    public function update(UnitRequest $request, Unit $unit)
    {
        DB::transaction(function () use ($request, $unit) {
            $unit->update($request->safe()->only(self::ATTRIBUTES));
            $this->syncAssignments($unit, $request);
        });

        return redirect()->route('units.show', $unit)
            ->with('status', 'Lokal został zaktualizowany.');
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();

        return redirect()->route('units.index')
            ->with('status', 'Lokal został usunięty.');
    }

    private function formData(Unit $unit): array
    {
        $assignedMeters = $unit->exists ? $unit->currentMeters() : collect();

        return [
            'unit' => $unit,
            'properties' => Property::orderBy('name')->get(),
            'tenants' => Tenant::orderBy('name')->get(),
            'metersByType' => collect(MeterType::cases())->mapWithKeys(
                fn (MeterType $type) => [$type->value => Meter::active()->withoutModules()->ofType($type)->orderBy('name')->get()],
            ),
            'currentTenantId' => $unit->exists ? $unit->currentTenant()?->id : null,
            'currentMeterIds' => collect(MeterType::cases())->mapWithKeys(
                fn (MeterType $type) => [$type->value => $assignedMeters->firstWhere('type', $type)?->id],
            ),
        ];
    }

    /**
     * Zmiana najemcy lub licznika zamyka poprzednie przypisanie datą zamiast je
     * nadpisywać — rozliczenia z przeszłości muszą dalej widzieć stan z epoki.
     */
    private function syncAssignments(Unit $unit, UnitRequest $request): void
    {
        $validFrom = $request->validated('valid_from') ?: now()->startOfMonth()->toDateString();

        $this->syncTenantAssignment($unit, $request->validated('tenant_id'), $validFrom);

        foreach (MeterType::cases() as $type) {
            $this->syncMeterAssignment($unit, $request->validated("meters.{$type->value}"), $type, $validFrom);
        }
    }

    private function syncTenantAssignment(Unit $unit, ?int $tenantId, string $validFrom): void
    {
        $current = $unit->tenantAssignments()->whereNull('valid_to')->latest('valid_from')->first();

        if ($current?->tenant_id === $tenantId) {
            return;
        }

        $current?->update(['valid_to' => $validFrom]);

        if ($tenantId !== null) {
            $unit->tenantAssignments()->create([
                'tenant_id' => $tenantId,
                'valid_from' => $validFrom,
            ]);
        }
    }

    private function syncMeterAssignment(Unit $unit, ?int $meterId, MeterType $type, string $validFrom): void
    {
        $current = $unit->meterAssignments()
            ->whereNull('valid_to')
            ->whereHas('meter', fn ($q) => $q->where('type', $type))
            ->latest('valid_from')
            ->first();

        if ($current?->meter_id === $meterId) {
            return;
        }

        $current?->update(['valid_to' => $validFrom]);

        if ($meterId !== null) {
            $unit->meterAssignments()->create([
                'meter_id' => $meterId,
                'valid_from' => $validFrom,
            ]);
        }
    }
}
