<?php

namespace App\Http\Controllers;

use App\Enums\MeterType;
use App\Http\Requests\ReadingRequest;
use App\Models\Meter;
use App\Models\Reading;
use App\Services\ModuleReadingConverter;
use App\Services\ReadingLookup;
use App\Services\ReadingSyncService;
use Illuminate\Http\Request;

class ReadingController extends Controller
{
    public function index(Request $request, ReadingSyncService $sync, ModuleReadingConverter $modules)
    {
        // Odczyty trafiają do tabeli wprost od kolektora, bez identyfikatora licznika.
        // Wejście na listę dowiązuje je po numerze seryjnym, nawet gdy harmonogram nie działa,
        // a odczyty nakładek przelicza na stan liczników mechanicznych.
        $linked = $sync->orphanedCount() > 0 ? $sync->linkOrphans() : 0;
        $modules->convertAll();

        $type = $request->query('type');

        $readings = Reading::query()
            ->with('meter')
            ->when($type, fn ($q) => $q->whereHas('meter', fn ($m) => $m->where('type', $type)))
            ->when($request->boolean('orphaned'), fn ($q) => $q->orphaned())
            ->orderByDesc('reading_date')
            ->orderByDesc('reading_timestamp')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('readings.index', [
            'readings' => $readings,
            'types' => MeterType::cases(),
            'activeType' => $type,
            'orphanedOnly' => $request->boolean('orphaned'),
            'orphanedCount' => Reading::orphaned()->count(),
            'linkedNow' => $linked,
        ]);
    }

    public function create(Request $request)
    {
        return view('readings.create', $this->formData(
            new Reading(['meter_id' => $request->integer('meter_id') ?: null]),
        ));
    }

    public function store(ReadingRequest $request)
    {
        Reading::create($request->validated() + ['source_table' => Reading::SOURCE_MANUAL]);

        return redirect()->route('readings.index')
            ->with('status', 'Odczyt został dodany.');
    }

    public function edit(Reading $reading)
    {
        return view('readings.edit', $this->formData($reading));
    }

    public function update(ReadingRequest $request, Reading $reading)
    {
        $reading->update($request->validated());

        return redirect()->route('readings.index')
            ->with('status', 'Odczyt został zaktualizowany.');
    }

    /**
     * Formularz dostaje ostatnie odczyty wszystkich liczników naraz, żeby panel
     * z historią przełączał się od razu po wyborze licznika, bez przeładowania.
     */
    private function formData(Reading $reading): array
    {
        $meters = Meter::active()->orderBy('name')->get();

        $history = app(ReadingLookup::class)
            ->recentForMeters($meters->pluck('id'), 6)
            ->map(fn ($readings) => $readings->map(fn (Reading $r) => [
                'id' => $r->id,
                'iso' => $r->reading_date->toDateString(),
                'date' => $r->measuredAtLabel(),
                'value' => (float) $r->consumption,
                'source' => $r->sourceLabel(),
            ])->values());

        return [
            'reading' => $reading,
            'meters' => $meters,
            'history' => $history,
            'units' => $meters->mapWithKeys(fn (Meter $m) => [$m->id => $m->type->unit()]),
        ];
    }

    public function destroy(Reading $reading)
    {
        $reading->delete();

        return redirect()->route('readings.index')
            ->with('status', 'Odczyt został usunięty.');
    }
}
