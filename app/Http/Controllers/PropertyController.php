<?php

namespace App\Http\Controllers;

use App\Http\Requests\PropertyRequest;
use App\Models\Property;

class PropertyController extends Controller
{
    public function index()
    {
        return view('properties.index', [
            'properties' => Property::withCount('units')->orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('properties.create', ['property' => new Property]);
    }

    public function store(PropertyRequest $request)
    {
        $property = Property::create($request->validated());

        return redirect()->route('properties.show', $property)
            ->with('status', 'Nieruchomość została dodana.');
    }

    public function show(Property $property)
    {
        $property->load(['units' => fn ($q) => $q->orderBy('description'), 'maintenanceCosts']);

        return view('properties.show', [
            'property' => $property,
            'tenantsByUnit' => $property->units->mapWithKeys(
                fn ($unit) => [$unit->id => $unit->currentTenant()],
            ),
        ]);
    }

    public function edit(Property $property)
    {
        return view('properties.edit', ['property' => $property]);
    }

    public function update(PropertyRequest $request, Property $property)
    {
        $property->update($request->validated());

        return redirect()->route('properties.show', $property)
            ->with('status', 'Nieruchomość została zaktualizowana.');
    }

    public function destroy(Property $property)
    {
        $property->delete();

        return redirect()->route('properties.index')
            ->with('status', 'Nieruchomość została usunięta.');
    }
}
