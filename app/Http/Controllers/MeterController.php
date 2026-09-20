<?php

namespace App\Http\Controllers;

use App\Enums\MeterType;
use App\Http\Requests\MeterRequest;
use App\Models\Meter;

class MeterController extends Controller
{
    public function index()
    {
        return view('meters.index', [
            'metersByType' => Meter::orderBy('name')->get()->groupBy(fn (Meter $meter) => $meter->type->value),
        ]);
    }

    public function create()
    {
        return view('meters.create', ['meter' => new Meter, 'types' => MeterType::cases()]);
    }

    public function store(MeterRequest $request)
    {
        $meter = Meter::create($request->validated());

        return redirect()->route('meters.show', $meter)
            ->with('status', 'Licznik został dodany.');
    }

    public function show(Meter $meter)
    {
        $unit = $meter->currentUnit();

        return view('meters.show', [
            'meter' => $meter,
            'unit' => $unit,
            'tenant' => $unit?->currentTenant(),
            'readings' => $meter->readings()->latest('reading_date')->limit(30)->get(),
        ]);
    }

    public function edit(Meter $meter)
    {
        return view('meters.edit', ['meter' => $meter, 'types' => MeterType::cases()]);
    }

    public function update(MeterRequest $request, Meter $meter)
    {
        $meter->update($request->validated());

        return redirect()->route('meters.show', $meter)
            ->with('status', 'Licznik został zaktualizowany.');
    }

    public function destroy(Meter $meter)
    {
        $meter->delete();

        return redirect()->route('meters.index')
            ->with('status', 'Licznik został usunięty.');
    }
}
