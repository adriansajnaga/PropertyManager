@extends('layouts.app')

@section('title', 'Najemcy')
@section('heading', 'Najemcy')
@section('subheading', 'Firmy wynajmujące lokale')

@section('actions')
    <flux:button variant="primary" icon="plus" :href="route('tenants.create')">Dodaj najemcę</flux:button>
@endsection

@section('content')
    <flux:card>
        <flux:table :paginate="$tenants">
            <flux:table.columns>
                <flux:table.column>Nazwa</flux:table.column>
                <flux:table.column>NIP</flux:table.column>
                <flux:table.column>E-mail</flux:table.column>
                <flux:table.column>Adres</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($tenants as $tenant)
                    <flux:table.row>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('tenants.show', $tenant)">{{ $tenant->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $tenant->nip ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $tenant->email ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $tenant->fullAddress() ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$tenant->is_active ? 'green' : 'zinc'">{{ $tenant->is_active ? 'aktywny' : 'nieaktywny' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <x-edit-button :href="route('tenants.edit', $tenant)" />
                            <x-delete-button :action="route('tenants.destroy', $tenant)" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">Brak najemców.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
@endsection
