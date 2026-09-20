@extends('layouts.app')

@section('title', 'Lokale')
@section('heading', 'Lokale')
@section('subheading', 'Lokale pogrupowane według nieruchomości')

@section('actions')
    <flux:button variant="primary" icon="plus" :href="route('units.create')">Dodaj lokal</flux:button>
@endsection

@section('content')
    @forelse ($properties as $property)
        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="lg">
                    <flux:link :href="route('properties.show', $property)" variant="subtle">{{ $property->name }}</flux:link>
                </flux:heading>
                <flux:badge size="sm">{{ $property->units->count() }} lokali</flux:badge>
            </div>

            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>Lokal</flux:table.column>
                    <flux:table.column align="end">Powierzchnia</flux:table.column>
                    <flux:table.column>Najemca</flux:table.column>
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($property->units as $unit)
                        @php($tenant = $unit->currentTenant())
                        <flux:table.row>
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('units.show', $unit)">{{ $unit->description }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $unit->area, 2, ',', ' ') }} m²</flux:table.cell>
                            <flux:table.cell>
                                @if ($tenant)
                                    {{ $tenant->name }}
                                @else
                                    <flux:badge size="sm" color="zinc">wolny</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <x-edit-button :href="route('units.edit', $unit)" />
                                <x-delete-button :action="route('units.destroy', $unit)" />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">Brak lokali.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @empty
        <flux:callout icon="information-circle" heading="Najpierw dodaj nieruchomość — lokale są do niej przypisywane." />
    @endforelse
@endsection
