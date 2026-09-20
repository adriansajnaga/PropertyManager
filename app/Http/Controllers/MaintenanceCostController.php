<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaintenanceCostRequest;
use App\Models\MaintenanceCost;
use App\Models\Property;

class MaintenanceCostController extends Controller
{
    public function index()
    {
        return view('maintenance-costs.index', [
            'costs' => MaintenanceCost::with('property')
                ->orderByDesc('year')
                ->orderBy('property_id')
                ->paginate(30),
        ]);
    }

    public function create()
    {
        return view('maintenance-costs.create', [
            'cost' => new MaintenanceCost(['year' => now()->year]),
            'properties' => Property::orderBy('name')->get(),
        ]);
    }

    public function store(MaintenanceCostRequest $request)
    {
        MaintenanceCost::create($request->validated());

        return redirect()->route('maintenance-costs.index')
            ->with('status', 'Koszt utrzymania został dodany.');
    }

    public function edit(MaintenanceCost $maintenanceCost)
    {
        return view('maintenance-costs.edit', [
            'cost' => $maintenanceCost,
            'properties' => Property::orderBy('name')->get(),
        ]);
    }

    public function update(MaintenanceCostRequest $request, MaintenanceCost $maintenanceCost)
    {
        $maintenanceCost->update($request->validated());

        return redirect()->route('maintenance-costs.index')
            ->with('status', 'Koszt utrzymania został zaktualizowany.');
    }

    public function destroy(MaintenanceCost $maintenanceCost)
    {
        $maintenanceCost->delete();

        return redirect()->route('maintenance-costs.index')
            ->with('status', 'Koszt utrzymania został usunięty.');
    }
}
