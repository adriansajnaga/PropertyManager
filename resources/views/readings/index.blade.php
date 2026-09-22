@extends('layouts.app')

@section('title', 'Odczyty')
@section('heading', 'Odczyty')
@section('subheading', 'Odczyty z synchronizacji i wprowadzone ręcznie')

@section('actions')
    <flux:button variant="primary" icon="plus" :href="route('readings.create')">Dodaj odczyt ręcznie</flux:button>
@endsection

@section('content')
    @if ($linkedNow > 0)
        <flux:callout variant="success" icon="link"
            heading="Przypisano odczyty po numerze seryjnym: {{ $linkedNow }}" />
    @endif
    <div class="flex flex-wrap items-center gap-2">
        <flux:button size="sm" :variant="! $activeType && ! $orphanedOnly ? 'primary' : 'outline'" :href="route('readings.index')">Wszystkie</flux:button>
        @foreach ($types as $type)
            <flux:button size="sm" :variant="$activeType === $type->value ? 'primary' : 'outline'" :href="route('readings.index', ['type' => $type->value])">
                {{ $type->label() }}
            </flux:button>
        @endforeach
        <flux:button size="sm" icon="exclamation-triangle" :variant="$orphanedOnly ? 'primary' : 'outline'" :href="route('readings.index', ['orphaned' => 1])">
            Nieprzypisane ({{ $orphanedCount }})
        </flux:button>
    </div>

    <flux:card>
        <flux:table :paginate="$readings">
            <flux:table.columns>
                <flux:table.column>Data</flux:table.column>
                <flux:table.column>Licznik</flux:table.column>
                <flux:table.column>Numer seryjny</flux:table.column>
                <flux:table.column align="end">Odczyt</flux:table.column>
                <flux:table.column>Źródło</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($readings as $reading)
                    <flux:table.row>
                        <flux:table.cell>{{ $reading->reading_date->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            @if ($reading->meter)
                                <flux:link :href="route('meters.show', $reading->meter)">{{ $reading->meter->name }}</flux:link>
                            @else
                                <flux:badge size="sm" color="amber">nieprzypisany</flux:badge>
                                <span class="ms-1 text-zinc-500 dark:text-zinc-400">{{ $reading->source_meter_name }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $reading->meter?->serial_number ?? $reading->source_meter_serial }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $reading->consumption, 2, ',', ' ') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$reading->isManual() ? 'sky' : ($reading->isCalculated() ? 'purple' : 'zinc')">
                                {{ $reading->sourceLabel() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <x-edit-button :href="route('readings.edit', $reading)" />
                            <x-delete-button :action="route('readings.destroy', $reading)" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">Brak odczytów.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
@endsection
