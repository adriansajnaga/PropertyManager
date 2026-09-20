@extends('layouts.app')

@section('title', 'Rachunki za media')
@section('heading', 'Rachunki za media')
@section('subheading', 'Ceny jednostkowe z faktur — podstawa wyceny wody i prądu')

@section('actions')
    <flux:button variant="primary" icon="plus" :href="route('utility-bills.create')">Dodaj rachunek</flux:button>
@endsection

@section('content')
    @forelse ($billsByMonth as $month => $bills)
        <flux:card>
            <flux:heading size="lg" class="capitalize">{{ \Carbon\Carbon::parse($month.'-01')->isoFormat('MMMM YYYY') }}</flux:heading>

            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>Rodzaj</flux:table.column>
                    <flux:table.column>Numer faktury</flux:table.column>
                    <flux:table.column align="end">Cena netto</flux:table.column>
                    <flux:table.column align="end">Zużycie</flux:table.column>
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($bills as $bill)
                        <flux:table.row>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$bill->type === \App\Enums\UtilityType::Water ? 'sky' : 'amber'"
                                    :icon="$bill->type === \App\Enums\UtilityType::Water ? 'beaker' : 'bolt'">{{ $bill->type->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell variant="strong">{{ $bill->invoice_number }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $bill->net_price, 4, ',', ' ') }} zł</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">
                                {{ $bill->consumption_value ? number_format((float) $bill->consumption_value, 2, ',', ' ') : '—' }}
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" icon="eye" :href="route('utility-bills.show', $bill)" tooltip="Podgląd i rozliczone zużycie" />
                                <x-edit-button :href="route('utility-bills.edit', $bill)" />
                                <x-delete-button :action="route('utility-bills.destroy', $bill)" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @empty
        <flux:callout icon="information-circle" heading="Brak rachunków — dodaj pierwszą fakturę za wodę lub prąd." />
    @endforelse
@endsection
