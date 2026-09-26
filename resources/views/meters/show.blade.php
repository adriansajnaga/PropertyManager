@extends('layouts.app')

@section('title', $meter->name)
@section('heading', $meter->name)
@section('subheading', $meter->type->label().' · '.$meter->serial_number.($meter->model ? ' · '.$meter->model : ''))

@section('actions')
    <flux:button icon="pencil-square" :href="route('meters.edit', $meter)">Edytuj</flux:button>
    <flux:button variant="primary" icon="plus" :href="route('readings.create', ['meter_id' => $meter->id])">Dodaj odczyt</flux:button>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:heading size="lg">Dane licznika</flux:heading>
            <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
                <x-detail label="Typ">{{ $meter->type->label() }} ({{ $meter->type->unit() }})</x-detail>
                <x-detail label="Numer seryjny" class="font-mono text-xs">{{ $meter->serial_number }}</x-detail>
                <x-detail label="Model">{{ $meter->model ?: '—' }}</x-detail>
                <x-detail label="Status">
                    <flux:badge size="sm" :color="$meter->is_active ? 'green' : 'zinc'">{{ $meter->is_active ? 'aktywny' : 'nieaktywny' }}</flux:badge>
                </x-detail>
                <x-detail label="Licznik główny">{{ $meter->is_main ? 'tak' : 'nie' }}</x-detail>
                <x-detail label="Podlicznik kotłowni">{{ $meter->is_boiler_supply ? 'tak' : 'nie' }}</x-detail>
                @if ($meter->is_module)
                    <x-detail label="Nakładka na liczniku">
                        @if ($meter->moduleFor)
                            <flux:link :href="route('meters.show', $meter->moduleFor)">{{ $meter->moduleFor->name }}</flux:link>
                        @else
                            <flux:badge size="sm" color="amber">brak przypisania</flux:badge>
                        @endif
                    </x-detail>
                    <x-detail label="Różnica wskazań">
                        {{ \App\Support\Format::reading($meter->module_offset, $meter->type->unit(), true) }}
                    </x-detail>
                @elseif ($modules->isNotEmpty())
                    <x-detail label="Nakładka radiowa">
                        @foreach ($modules as $module)
                            <div>
                                <flux:link :href="route('meters.show', $module)">{{ $module->serial_number }}</flux:link>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    różnica {{ \App\Support\Format::reading($module->module_offset, $meter->type->unit(), true) }}
                                </span>
                            </div>
                        @endforeach
                    </x-detail>
                @endif
                <x-detail label="Lokal">
                    @if ($unit)
                        <flux:link :href="route('units.show', $unit)">{{ $unit->description }}</flux:link>
                    @else
                        —
                    @endif
                </x-detail>
                <x-detail label="Najemca">
                    @if ($tenant)
                        <flux:link :href="route('tenants.show', $tenant)">{{ $tenant->name }}</flux:link>
                    @else
                        —
                    @endif
                </x-detail>
            </dl>
        </flux:card>

        <flux:card class="lg:col-span-2">
            <flux:heading size="lg">Ostatnie odczyty</flux:heading>
            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>Data</flux:table.column>
                    <flux:table.column align="end">Odczyt</flux:table.column>
                    <flux:table.column>HEX</flux:table.column>
                    <flux:table.column>Źródło</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($readings as $reading)
                        <flux:table.row>
                            <flux:table.cell>{{ $reading->measuredAtLabel() }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">
                                {{ \App\Support\Format::reading($reading->consumption, $meter->type->unit(), true) }}
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $reading->raw_hex ?: '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$reading->isManual() ? 'sky' : ($reading->isCalculated() ? 'purple' : 'zinc')">
                                    {{ $reading->sourceLabel() }}
                                </flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">Brak odczytów.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
@endsection
