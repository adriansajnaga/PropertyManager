@extends('layouts.app')

@section('title', 'Koszty ciepła')
@section('heading', 'Rozliczenia kosztów ciepła')
@section('subheading', 'Miesięczna cena za 1 GJ wyliczana z zużycia prądu kotłowni')

@section('actions')
    <flux:button variant="primary" icon="plus" :href="route('heat-settlements.create')">Utwórz rozliczenie</flux:button>
@endsection

@section('content')
    <flux:card>
        <flux:table :paginate="$settlements">
            <flux:table.columns>
                <flux:table.column>Miesiąc</flux:table.column>
                <flux:table.column>Faktura za prąd</flux:table.column>
                <flux:table.column align="end">Zużycie kotłowni</flux:table.column>
                <flux:table.column align="end">Suma GJ</flux:table.column>
                <flux:table.column align="end">Cena za GJ</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($settlements as $settlement)
                    <flux:table.row>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('heat-settlements.show', $settlement)">{{ $settlement->month->format('m.Y') }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $settlement->electricBill?->invoice_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $settlement->boiler_kwh_consumed, 2, ',', ' ') }} kWh</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $settlement->total_gj_consumed, 4, ',', ' ') }} GJ</flux:table.cell>
                        <flux:table.cell align="end" variant="strong" class="tabular-nums">{{ number_format((float) $settlement->price_per_gj, 2, ',', ' ') }} zł</flux:table.cell>
                        <flux:table.cell align="end">
                            <x-delete-button :action="route('heat-settlements.destroy', $settlement)" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">Brak rozliczeń kosztów ciepła.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
@endsection
