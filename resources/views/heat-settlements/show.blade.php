@extends('layouts.app')

@section('title', 'Koszty ciepła '.$settlement->month->format('m.Y'))
@section('heading', 'Koszty ciepła — '.$settlement->month->format('m.Y'))
@section('subheading', 'Faktura '.($settlement->electricBill?->invoice_number ?? '—'))

@section('actions')
    <flux:button icon="arrow-path"
        :href="route('heat-settlements.create', ['month' => $settlement->month->format('Y-m'), 'electric_bill_id' => $settlement->electric_bill_id])">
        Przelicz ponownie
    </flux:button>
@endsection

@section('content')
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Zużycie kotłowni', number_format((float) $settlement->boiler_kwh_consumed, 2, ',', ' ').' kWh'],
            ['Suma zużytych GJ', number_format((float) $settlement->total_gj_consumed, 4, ',', ' ').' GJ'],
            ['Cena za 1 GJ', number_format((float) $settlement->price_per_gj, 2, ',', ' ').' zł'],
        ] as [$label, $value])
            <flux:card>
                <flux:subheading>{{ $label }}</flux:subheading>
                <flux:heading size="xl" class="mt-2 tabular-nums">{{ $value }}</flux:heading>
            </flux:card>
        @endforeach
    </div>

    <flux:card class="max-w-2xl">
        <flux:heading size="lg">Kotłownia</flux:heading>
        <flux:subheading>Zużycie prądu, z którego wyliczona jest cena za 1 GJ</flux:subheading>
        <dl class="mt-2 divide-y divide-zinc-100 text-sm dark:divide-white/10">
            <x-detail label="Podlicznik">{{ $settlement->boiler_meter_name ?? '— brak —' }}</x-detail>
            <x-detail label="Numer seryjny" class="font-mono text-xs">{{ $settlement->boiler_meter_serial ?? '—' }}</x-detail>
            <x-detail label="Odczyt na {{ $settlement->month->format('d.m.Y') }}" class="tabular-nums">
                {{ $settlement->boiler_start_reading !== null ? number_format((float) $settlement->boiler_start_reading, 2, ',', ' ').' kWh' : '—' }}
            </x-detail>
            <x-detail label="Odczyt na {{ $settlement->month->copy()->addMonth()->format('d.m.Y') }}" class="tabular-nums">
                {{ $settlement->boiler_end_reading !== null ? number_format((float) $settlement->boiler_end_reading, 2, ',', ' ').' kWh' : '—' }}
            </x-detail>
            <x-detail label="Zużycie" class="tabular-nums">{{ number_format((float) $settlement->boiler_kwh_consumed, 2, ',', ' ') }} kWh</x-detail>
            <x-detail label="Cena prądu" class="tabular-nums">{{ number_format((float) $settlement->electricBill?->net_price, 4, ',', ' ') }} zł/kWh</x-detail>
            <x-detail label="Faktura">{{ $settlement->electricBill?->invoice_number ?? '—' }}</x-detail>
        </dl>
        <flux:text class="pt-2 text-xs">Cena za GJ = (cena prądu × kWh kotłowni) ÷ suma GJ</flux:text>
    </flux:card>

    <flux:card>
        <flux:heading size="lg">Liczniki ciepła</flux:heading>
        <flux:subheading>Dane zamrożone w momencie zapisu rozliczenia</flux:subheading>
        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>Licznik</flux:table.column>
                <flux:table.column>Numer seryjny</flux:table.column>
                <flux:table.column align="end">Początkowy</flux:table.column>
                <flux:table.column align="end">Końcowy</flux:table.column>
                <flux:table.column align="end">GJ</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($settlement->lines as $line)
                    <flux:table.row>
                        <flux:table.cell variant="strong">{{ $line->meter_name }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $line->meter_serial }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $line->start_reading !== null ? number_format((float) $line->start_reading, 4, ',', ' ') : '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $line->end_reading !== null ? number_format((float) $line->end_reading, 4, ',', ' ') : '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $line->gj_consumed, 4, ',', ' ') }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
@endsection
