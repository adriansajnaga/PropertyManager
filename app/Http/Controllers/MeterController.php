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
        return view('meters.create', $this->formData(new Meter));
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
            'modules' => $meter->modules()->orderBy('name')->get(),
        ]);
    }

    public function edit(Meter $meter)
    {
        return view('meters.edit', $this->formData($meter));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Meter $meter): array
    {
        return [
            'meter' => $meter,
            'types' => MeterType::cases(),
            // Nakładkę można zamontować na dowolnym liczniku mechanicznym poza nią samą.
            'hostMeters' => Meter::query()
                ->withoutModules()
                ->when($meter->exists, fn ($query) => $query->whereKeyNot($meter->id))
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
        ];
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
