@extends('layouts.app')

@section('title', 'Liczniki')
@section('heading', 'Liczniki')
@section('subheading', 'Liczniki wody, prądu i ciepła oraz ich przypisanie do lokali')

@section('actions')
    <flux:button variant="primary" icon="plus" :href="route('meters.create')">Dodaj licznik</flux:button>
@endsection

@section('content')
    @foreach (\App\Enums\MeterType::cases() as $type)
        @php($meters = $metersByType[$type->value] ?? collect())
        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ $type->label() }}</flux:heading>
                <flux:badge size="sm">{{ $meters->count() }} szt.</flux:badge>
            </div>

            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>Nazwa</flux:table.column>
                    <flux:table.column>Numer seryjny</flux:table.column>
                    <flux:table.column>Lokal</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($meters as $meter)
                        <flux:table.row>
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('meters.show', $meter)">{{ $meter->name }}</flux:link>
                                @if ($meter->is_main)
                                    <flux:badge size="sm" color="purple" class="ms-2">główny</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="font-mono text-xs">{{ $meter->serial_number }}</div>
                                @if ($meter->model)
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $meter->model }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $meter->currentUnit()?->description ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$meter->is_active ? 'green' : 'zinc'">{{ $meter->is_active ? 'aktywny' : 'nieaktywny' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <x-edit-button :href="route('meters.edit', $meter)" />
                                <x-delete-button :action="route('meters.destroy', $meter)"
                                    confirm="Usunięcie licznika odłączy powiązane odczyty. Kontynuować?" />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">Brak liczników tego typu.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endforeach
@endsection
