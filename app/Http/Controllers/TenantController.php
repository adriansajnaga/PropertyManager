<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantRequest;
use App\Models\Reading;
use App\Models\Tenant;
use App\Services\RentPaymentSummary;
use App\Services\Sms\SmsGateway;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index()
    {
        return view('tenants.index', [
            'tenants' => Tenant::orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('tenants.create', ['tenant' => new Tenant]);
    }

    public function store(TenantRequest $request)
    {
        $tenant = Tenant::create($request->validated());

        return redirect()->route('tenants.show', $tenant)
            ->with('status', 'Najemca został dodany.');
    }

    public function show(Request $request, Tenant $tenant, RentPaymentSummary $payments, SmsGateway $sms)
    {
        $units = $tenant->currentUnits();
        $meters = $units->flatMap(fn ($unit) => $unit->currentMeters());

        $readings = Reading::query()
            ->with('meter')
            ->whereIn('meter_id', $meters->pluck('id'))
            ->where('reading_date', '>=', now()->subMonths(2)->toDateString())
            ->orderByDesc('reading_date')
            ->get()
            ->groupBy('meter_id');

        return view('tenants.show', [
            'tenant' => $tenant,
            'units' => $units,
            'meters' => $meters,
            'readingsByMeter' => $readings,
            'settlements' => $tenant->settlements()->with('unit')->latest('month')->limit(12)->get(),
            'payments' => $payments->forTenant($tenant, (int) $request->query('year', now()->year)),
            'smsReady' => $sms->isConfigured(),
            'smsTestMode' => $sms->isTestMode(),
        ]);
    }

    public function edit(Tenant $tenant)
    {
        return view('tenants.edit', ['tenant' => $tenant]);
    }

    public function update(TenantRequest $request, Tenant $tenant)
    {
        $tenant->update($request->validated());

        return redirect()->route('tenants.show', $tenant)
            ->with('status', 'Dane najemcy zostały zaktualizowane.');
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return redirect()->route('tenants.index')
            ->with('status', 'Najemca został usunięty.');
    }
}
