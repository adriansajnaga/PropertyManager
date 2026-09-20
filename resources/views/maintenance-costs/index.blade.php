@extends('layouts.app')

@section('title', 'Koszty utrzymania')
@section('heading', 'Roczne koszty utrzymania')
@section('subheading', 'Rozdzielane na lokale proporcjonalnie do powierzchni')

@section('actions')
    <flux:button variant="primary" icon="plus" :href="route('maintenance-costs.create')">Dodaj koszt</flux:button>
@endsection

@section('content')
    <flux:card>
        <flux:table :paginate="$costs">
            <flux:table.columns>
                <flux:table.column>Rok</flux:table.column>
                <flux:table.column>Nieruchomość</flux:table.column>
                <flux:table.column>Opis kosztu</flux:table.column>
                <flux:table.column align="end">Koszt roczny</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($costs as $cost)
                    <flux:table.row>
                        <flux:table.cell>{{ $cost->year }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('properties.show', $cost->property)">{{ $cost->property->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell variant="strong">{{ $cost->description }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $cost->annual_cost, 2, ',', ' ') }} zł</flux:table.cell>
                        <flux:table.cell align="end">
                            <x-edit-button :href="route('maintenance-costs.edit', $cost)" />
                            <x-delete-button :action="route('maintenance-costs.destroy', $cost)" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">Brak kosztów utrzymania.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
@endsection
