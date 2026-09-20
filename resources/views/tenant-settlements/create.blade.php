@extends('layouts.app')

@section('title', $existing ? 'Edycja rozliczenia' : 'Nowe rozliczenie')
@section('heading', $unit->description.' — '.$month->isoFormat('MMMM YYYY'))
@section('subheading', ($existing ? 'Edycja szkicu '.$existing->number : 'Podgląd rozliczenia przed zapisem').' · '.$unit->property->name)

@section('content')
    @php
        $billLabel = fn ($bill, $unitLabel) => $bill->month->format('m.Y').' · '.$bill->invoice_number.' · '
            .number_format((float) $bill->net_price, 4, ',', ' ').' zł/'.$unitLabel;
        $selectedWater = $preview['bills']['water'];
        $selectedElectric = $preview['bills']['electric'];
    @endphp

    <flux:card>
        <form method="GET" action="{{ route('tenant-settlements.create') }}" class="space-y-6">
            <input type="hidden" name="unit_id" value="{{ $unit->id }}">

            <div class="grid gap-6 md:grid-cols-[11rem_1fr_1fr]">
                {{-- Nowy miesiąc = nowe rachunki domyślne, więc wyłączone listy nie są wysyłane. --}}
                <flux:input name="month" type="month" label="Miesiąc rozliczeniowy" :value="$month->format('Y-m')"
                    onchange="this.form.water_bill_id.disabled = true; this.form.electric_bill_id.disabled = true; this.form.submit()" />

                <flux:select name="water_bill_id" label="Rachunek za wodę" onchange="this.form.submit()">
                    @foreach ($waterBills as $bill)
                        <flux:select.option :value="$bill->id" :selected="$selectedWater?->id === $bill->id">
                            {{ $billLabel($bill, 'm³') }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select name="electric_bill_id" label="Rachunek za prąd" onchange="this.form.submit()">
                    @foreach ($electricBills as $bill)
                        <flux:select.option :value="$bill->id" :selected="$selectedElectric?->id === $bill->id">
                            {{ $billLabel($bill, 'kWh') }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <flux:button type="submit" icon="arrow-path">Przelicz podgląd</flux:button>
                <flux:text>
                    Okres zużycia: {{ \Carbon\Carbon::parse($preview['period_start'])->format('d.m.Y') }}
                    – {{ \Carbon\Carbon::parse($preview['period_end'])->format('d.m.Y') }}.
                    Rachunek może pochodzić z innego miesiąca — np. faktura za wodę wystawiona za kilka miesięcy.
                </flux:text>
            </div>
        </form>
    </flux:card>

    @foreach ($preview['warnings'] as $warning)
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="$warning" />
    @endforeach

    @include('tenant-settlements.lines-table', [
        'lines' => collect($preview['lines']),
        'periodStart' => \Carbon\Carbon::parse($preview['period_start']),
        'periodEnd' => \Carbon\Carbon::parse($preview['period_end']),
        'totalNet' => $preview['total_net'],
        'totalVat' => $preview['total_vat'],
        'totalGross' => $preview['total_gross'],
        'vatRate' => $preview['vat_rate'],
    ])

    <form method="POST" action="{{ route('tenant-settlements.store') }}">
        @csrf
        <input type="hidden" name="unit_id" value="{{ $unit->id }}">
        <input type="hidden" name="month" value="{{ $preview['month'] }}">
        <input type="hidden" name="water_bill_id" value="{{ $selectedWater?->id }}">
        <input type="hidden" name="electric_bill_id" value="{{ $selectedElectric?->id }}">
        <flux:button type="submit" variant="primary" icon="check">{{ $existing ? 'Zapisz zmiany' : 'Zapisz rozliczenie' }}</flux:button>
    </form>
@endsection
