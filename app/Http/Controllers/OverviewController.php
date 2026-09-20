<?php

namespace App\Http\Controllers;

use App\Models\Meter;
use App\Models\Property;
use App\Models\Reading;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Models\TenantSettlement;
use App\Models\Unit;
use App\Services\ReadingSyncService;
use App\Services\RentAccrualService;

class OverviewController extends Controller
{
    public function __invoke(ReadingSyncService $sync, RentAccrualService $accrual)
    {
        $accrual->run();

        return view('overview', [
            'propertyCount' => Property::count(),
            'unitCount' => Unit::count(),
            'tenantCount' => Tenant::where('is_active', true)->count(),
            'meterCount' => Meter::where('is_active', true)->count(),
            'orphanedCount' => $sync->orphanedCount(),
            'overdueRent' => RentCharge::overdue()->sum('amount'),
            'overdueRentCount' => RentCharge::overdue()->count(),
            'staleStates' => $sync->staleStates(),
            'latestReadings' => Reading::with('meter')->latest('reading_date')->limit(10)->get(),
            'recentSettlements' => TenantSettlement::with('unit.property', 'tenant')->latest('month')->limit(10)->get(),
        ]);
    }
}
