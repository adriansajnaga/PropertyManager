@extends('layouts.app')

@section('title', 'Nieruchomości')
@section('heading', 'Nieruchomości')
@section('subheading', 'Budynki i ich powierzchnia do wynajęcia')

@section('actions')
    <flux:button variant="primary" icon="plus" :href="route('properties.create')">Dodaj nieruchomość</flux:button>
@endsection

@section('content')
    <flux:card>
        <flux:table :paginate="$properties">
            <flux:table.columns>
                <flux:table.column>Nazwa</flux:table.column>
                <flux:table.column>Adres</flux:table.column>
                <flux:table.column align="end">Powierzchnia</flux:table.column>
                <flux:table.column align="end">Lokale</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($properties as $property)
                    <flux:table.row>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('properties.show', $property)">{{ $property->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $property->address }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $property->total_area, 2, ',', ' ') }} m²</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $property->units_count }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <x-edit-button :href="route('properties.edit', $property)" />
                            <x-delete-button :action="route('properties.destroy', $property)"
                                confirm="Usunięcie nieruchomości usunie też jej lokale i koszty utrzymania. Kontynuować?" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">Brak nieruchomości.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
@endsection
