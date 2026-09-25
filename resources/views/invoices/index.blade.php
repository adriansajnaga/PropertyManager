@extends('layouts.app')

@section('title', 'Faktury')
@section('heading', 'Faktury')
@section('subheading', 'Faktury za czynsz wystawiane z naliczeń')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <form method="GET" class="w-40">
            <flux:select name="year" label="Rok" onchange="this.form.submit()">
                @foreach ($years as $option)
                    <flux:select.option :value="$option" :selected="$option === $year">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        </form>

        <div class="flex flex-wrap gap-6 text-sm">
            <div>
                <flux:subheading>Netto w {{ $year }}</flux:subheading>
                <flux:heading size="lg" class="tabular-nums">{{ \App\Support\Format::money($netTotal) }} zł</flux:heading>
            </div>
            <div>
                <flux:subheading>Brutto</flux:subheading>
                <flux:heading size="lg" class="tabular-nums">{{ \App\Support\Format::money($grossTotal) }} zł</flux:heading>
            </div>
        </div>
    </div>

    @if ($uncharged->isNotEmpty())
        <flux:card>
            <flux:heading size="lg">Czynsze bez faktury</flux:heading>
            <flux:subheading>Naliczenia, do których nie wystawiono jeszcze dokumentu.</flux:subheading>

            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>Miesiąc</flux:table.column>
                    <flux:table.column>Lokal</flux:table.column>
                    <flux:table.column>Najemca</flux:table.column>
                    <flux:table.column align="end">Kwota</flux:table.column>
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($uncharged as $charge)
                        <flux:table.row>
                            <flux:table.cell variant="strong" class="capitalize">{{ $charge->month->isoFormat('MMMM YYYY') }}</flux:table.cell>
                            <flux:table.cell>{{ $charge->unit->description }}</flux:table.cell>
                            <flux:table.cell>{{ $charge->tenant?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::money($charge->amount) }} zł</flux:table.cell>
                            <flux:table.cell align="end">
                                <form method="POST" action="{{ route('invoices.store') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="rent_charge_id" value="{{ $charge->id }}">
                                    <flux:button type="submit" size="sm" icon="document-plus">Wystaw fakturę</flux:button>
                                </form>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    <flux:card>
        <flux:heading size="lg">Wystawione</flux:heading>

        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>Numer</flux:table.column>
                <flux:table.column>Data</flux:table.column>
                <flux:table.column>Nabywca</flux:table.column>
                <flux:table.column>Lokal</flux:table.column>
                <flux:table.column align="end">Netto</flux:table.column>
                <flux:table.column align="end">Brutto</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($invoices as $invoice)
                    <flux:table.row>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('invoices.show', $invoice)">{{ $invoice->number }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $invoice->issued_on->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->tenant?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->unit?->description ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::money($invoice->total_net) }} zł</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::money($invoice->total_gross) }} zł</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$invoice->status->color()">{{ $invoice->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="eye" :href="route('invoices.show', $invoice)" tooltip="Podgląd" />
                            @unless ($invoice->isInKsef())
                                <x-delete-button :action="route('invoices.destroy', $invoice)"
                                    confirm="Usunąć fakturę {{ $invoice->number }}? Numer zostanie zwolniony." />
                            @endunless
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8">Brak faktur w {{ $year }}.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
@endsection
