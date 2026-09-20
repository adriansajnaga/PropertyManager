@extends('layouts.app')

@section('title', 'Nowe rozliczenie kosztów ciepła')
@section('heading', 'Rozliczenie kosztów ciepła')
@section('subheading', 'Wybierz miesiąc i fakturę za prąd — wyliczenie pojawi się poniżej')

@section('content')
    <flux:card>
        <form method="GET" action="{{ route('heat-settlements.create') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-44">
                <flux:input name="month" type="month" label="Miesiąc" :value="$month->format('Y-m')" />
            </div>

            <div class="min-w-72 flex-1">
                <flux:select name="electric_bill_id" label="Faktura za prąd" placeholder="— wybierz —">
                    @foreach ($bills as $bill)
                        <flux:select.option :value="$bill->id" :selected="$selectedBill?->id === $bill->id">
                            {{ $bill->month->format('m.Y') }} · {{ $bill->invoice_number }} · {{ number_format((float) $bill->net_price, 4, ',', ' ') }} zł/kWh
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:button type="submit" icon="arrow-path">Przelicz podgląd</flux:button>
        </form>
    </flux:card>

    @if (! $selectedBill)
        <flux:callout icon="information-circle" heading="Wybierz fakturę za prąd, aby zobaczyć wyliczenie ceny za GJ." />
    @else
        @foreach ($preview['warnings'] as $warning)
            <flux:callout variant="warning" icon="exclamation-triangle" :heading="$warning" />
        @endforeach

        <div class="grid gap-4 sm:grid-cols-3">
            <flux:card>
                <flux:subheading>Suma zużytych GJ</flux:subheading>
                <flux:heading size="xl" class="mt-2 tabular-nums">{{ number_format($preview['total_gj_consumed'], 4, ',', ' ') }} GJ</flux:heading>
            </flux:card>
            <flux:card>
                <flux:subheading>Zużycie prądu kotłowni</flux:subheading>
                <flux:heading size="xl" class="mt-2 tabular-nums">{{ number_format($preview['boiler_kwh_consumed'], 2, ',', ' ') }} kWh</flux:heading>
            </flux:card>
            <flux:card class="border-zinc-800! dark:border-white!">
                <flux:subheading>Cena za 1 GJ</flux:subheading>
                <flux:heading size="xl" class="mt-2 tabular-nums">{{ number_format($preview['price_per_gj'], 2, ',', ' ') }} zł</flux:heading>
            </flux:card>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <flux:card class="lg:col-span-2">
                <flux:heading size="lg">Liczniki ciepła — {{ $month->format('m.Y') }}</flux:heading>
                <flux:table class="mt-2">
                    <flux:table.columns>
                        <flux:table.column>Licznik</flux:table.column>
                        <flux:table.column>Numer seryjny</flux:table.column>
                        <flux:table.column align="end">Początkowy</flux:table.column>
                        <flux:table.column align="end">Końcowy</flux:table.column>
                        <flux:table.column align="end">Zużycie (GJ)</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($preview['lines'] as $line)
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ $line['meter_name'] }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ $line['meter_serial'] }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $line['start_reading'] !== null ? number_format((float) $line['start_reading'], 4, ',', ' ') : '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $line['end_reading'] !== null ? number_format((float) $line['end_reading'], 4, ',', ' ') : '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ number_format($line['gj_consumed'], 4, ',', ' ') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                        <flux:table.row>
                            <flux:table.cell colspan="4" variant="strong">Razem</flux:table.cell>
                            <flux:table.cell align="end" variant="strong" class="tabular-nums">{{ number_format($preview['total_gj_consumed'], 4, ',', ' ') }}</flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            <flux:card class="space-y-2">
                <flux:heading size="lg">Kotłownia</flux:heading>
                <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
                    <x-detail label="Podlicznik">{{ $preview['boiler_meter']?->name ?? '— brak —' }}</x-detail>
                    <x-detail label="Odczyt początkowy" class="tabular-nums">{{ $preview['boiler_start_reading'] !== null ? number_format((float) $preview['boiler_start_reading'], 2, ',', ' ').' kWh' : '—' }}</x-detail>
                    <x-detail label="Odczyt końcowy" class="tabular-nums">{{ $preview['boiler_end_reading'] !== null ? number_format((float) $preview['boiler_end_reading'], 2, ',', ' ').' kWh' : '—' }}</x-detail>
                    <x-detail label="Cena prądu" class="tabular-nums">{{ number_format((float) $selectedBill->net_price, 4, ',', ' ') }} zł/kWh</x-detail>
                    <x-detail label="Faktura">{{ $selectedBill->invoice_number }}</x-detail>
                </dl>
                <flux:text class="pt-2 text-xs">Cena za GJ = (cena prądu × kWh kotłowni) ÷ suma GJ</flux:text>
            </flux:card>
        </div>

        <form method="POST" action="{{ route('heat-settlements.store') }}">
            @csrf
            <input type="hidden" name="month" value="{{ $month->toDateString() }}">
            <input type="hidden" name="electric_bill_id" value="{{ $selectedBill->id }}">
            <flux:button type="submit" variant="primary" icon="check">Zapisz rozliczenie</flux:button>
        </form>
    @endif
@endsection
