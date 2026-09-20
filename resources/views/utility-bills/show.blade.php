@extends('layouts.app')

@section('title', 'Rachunek '.$bill->invoice_number)
@section('heading', $bill->invoice_number)
@section('subheading', $bill->type->label().' · '.$bill->month->isoFormat('MMMM YYYY'))

@section('actions')
    <flux:button icon="pencil-square" :href="route('utility-bills.edit', $bill)">Edytuj</flux:button>
@endsection

@section('content')
    @php
        $invoiced = (float) ($bill->consumption_value ?? 0);
        $percent = $invoiced > 0 ? round($settledTotal / $invoiced * 100, 1) : null;
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:heading size="lg">Dane rachunku</flux:heading>
            <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
                <x-detail label="Rodzaj">{{ $bill->type->label() }}</x-detail>
                <x-detail label="Miesiąc">{{ $bill->month->format('m.Y') }}</x-detail>
                <x-detail label="Zużycie z faktury" class="tabular-nums">
                    {{ $bill->consumption_value !== null ? \App\Support\Format::reading($bill->consumption_value, $unitLabel, true) : '—' }}
                </x-detail>
                <x-detail label="Kwota netto" class="tabular-nums">
                    {{ $bill->net_amount !== null ? \App\Support\Format::money($bill->net_amount).' zł' : '—' }}
                </x-detail>
                <x-detail label="Cena w rozliczeniach" class="tabular-nums">
                    {{ \App\Support\Format::money($bill->net_price, 4) }} zł/{{ $unitLabel }}
                </x-detail>
            </dl>
        </flux:card>

        @if ($showCoverage)
            <flux:card class="lg:col-span-2 space-y-2">
                <flux:heading size="lg">Ile z tego rachunku zostało rozliczone</flux:heading>

                <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
                    <x-detail label="Rozliczone na lokale" class="tabular-nums">{{ \App\Support\Format::reading($settledByUnits, $unitLabel, true) }}</x-detail>
                    <x-detail label="Pompa ciepła (przez cenę za GJ)" class="tabular-nums">{{ \App\Support\Format::reading($settledByBoiler, $unitLabel, true) }}</x-detail>
                    <x-detail label="Razem rozliczone" class="tabular-nums">{{ \App\Support\Format::reading($settledTotal, $unitLabel, true) }}</x-detail>
                    <x-detail label="Nierozliczona reszta" class="tabular-nums">
                        {{ $bill->consumption_value !== null ? \App\Support\Format::reading($invoiced - $settledTotal, $unitLabel, true) : '—' }}
                    </x-detail>
                </dl>

                @if ($percent !== null)
                    <div class="pt-2">
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="text-zinc-500 dark:text-zinc-400">Pokrycie faktury</span>
                            <span class="font-semibold tabular-nums">{{ \App\Support\Format::money($percent, 1) }}%</span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                            <div @class([
                                    'h-full rounded-full',
                                    'bg-green-600' => $percent >= 90 && $percent <= 105,
                                    'bg-amber-500' => ($percent >= 60 && $percent < 90) || ($percent > 105 && $percent <= 120),
                                    'bg-red-600' => $percent < 60 || $percent > 120,
                                ])
                                style="width: {{ min(100, max(0, $percent)) }}%"></div>
                        </div>
                    </div>
                @else
                    <flux:text>Uzupełnij zużycie z faktury, aby zobaczyć, jaka jej część została rozliczona.</flux:text>
                @endif
            </flux:card>
        @endif
    </div>

    <flux:card>
        <flux:heading size="lg">Rozliczenia korzystające z tego rachunku</flux:heading>

        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>Miesiąc</flux:table.column>
                <flux:table.column>Lokal</flux:table.column>
                <flux:table.column>Najemca</flux:table.column>
                <flux:table.column align="end">Zużycie</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($settlements as $settlement)
                    <flux:table.row>
                        <flux:table.cell>{{ $settlement->month->format('m.Y') }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('tenant-settlements.show', $settlement)">{{ $settlement->unit->description }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $settlement->tenant?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">
                            {{ \App\Support\Format::reading($settlement->lines->sum('consumption'), $unitLabel, true) }}
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:badge size="sm" :color="$settlement->isFinal() ? 'green' : 'yellow'">{{ $settlement->status->label() }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">Żadne rozliczenie najemcy nie korzysta jeszcze z tego rachunku.</flux:table.cell>
                    </flux:table.row>
                @endforelse

                @foreach ($heatSettlements as $heat)
                    <flux:table.row>
                        <flux:table.cell>{{ $heat->month->format('m.Y') }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('heat-settlements.show', $heat)">Rozliczenie kosztów ciepła</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $heat->boiler_meter_name ?? 'pompa ciepła' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">
                            {{ \App\Support\Format::reading($heat->boiler_kwh_consumed, $unitLabel, true) }}
                        </flux:table.cell>
                        <flux:table.cell align="end"></flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
@endsection
