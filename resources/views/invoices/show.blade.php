@extends('layouts.app')

@section('title', 'Faktura '.$invoice->number)
@section('heading', 'Faktura '.$invoice->number)
@section('subheading', 'Wystawiona '.$invoice->issued_on->format('d.m.Y').' · termin płatności '.$invoice->due_on->format('d.m.Y'))

@section('actions')
    <flux:button icon="document-arrow-down" :href="route('invoices.pdf', $invoice)">PDF</flux:button>

    @unless ($invoice->isInKsef())
        <form method="POST" action="{{ route('invoices.send', $invoice) }}" class="inline">
            @csrf
            <flux:button type="submit" variant="primary" icon="paper-airplane">Wyślij do KSeF</flux:button>
        </form>
    @endunless

    @unless ($invoice->isInKsef())
        <x-delete-button :action="route('invoices.destroy', $invoice)" label="Usuń fakturę"
            confirm="Usunąć fakturę {{ $invoice->number }}? Numer zostanie zwolniony." />
    @endunless
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:heading size="lg">Nabywca</flux:heading>
            <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
                <x-detail label="Nazwa">
                    @if ($invoice->tenant)
                        <flux:link :href="route('tenants.show', $invoice->tenant)">{{ $invoice->tenant->name }}</flux:link>
                    @else
                        —
                    @endif
                </x-detail>
                <x-detail label="NIP">{{ $invoice->tenant?->nip ?: '—' }}</x-detail>
                <x-detail label="Adres">{{ $invoice->tenant?->fullAddress() ?: '—' }}</x-detail>
                <x-detail label="Lokal">
                    @if ($invoice->unit)
                        <flux:link :href="route('units.show', $invoice->unit)">{{ $invoice->unit->description }}</flux:link>
                    @else
                        —
                    @endif
                </x-detail>
            </dl>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:heading size="lg">Dokument</flux:heading>
            <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
                <x-detail label="Data wystawienia">{{ $invoice->issued_on->format('d.m.Y') }}</x-detail>
                <x-detail label="Data sprzedaży">{{ $invoice->sold_on->format('d.m.Y') }}</x-detail>
                <x-detail label="Termin płatności">{{ $invoice->due_on->format('d.m.Y') }}</x-detail>
                <x-detail label="Naliczenie czynszu">
                    @if ($invoice->rentCharge)
                        <flux:link :href="route('rent-charges.edit', $invoice->rentCharge)">
                            {{ $invoice->rentCharge->month->format('m.Y') }}
                        </flux:link>
                    @else
                        —
                    @endif
                </x-detail>
            </dl>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:heading size="lg">KSeF</flux:heading>
            <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
                <x-detail label="Status">
                    <flux:badge size="sm" :color="$invoice->status->color()">{{ $invoice->status->label() }}</flux:badge>
                </x-detail>
                <x-detail label="Numer KSeF" class="font-mono text-xs">{{ $invoice->ksef_number ?: '—' }}</x-detail>
                <x-detail label="Wysłano">{{ $invoice->ksef_sent_at?->format('d.m.Y, H:i') ?: '—' }}</x-detail>
            </dl>

            @if ($invoice->ksef_error)
                <flux:callout variant="danger" icon="x-circle" :heading="$invoice->ksef_error" />
            @endif
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="lg">Pozycje</flux:heading>

        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>Lp.</flux:table.column>
                <flux:table.column>Nazwa</flux:table.column>
                <flux:table.column align="end">Ilość</flux:table.column>
                <flux:table.column align="end">Cena netto</flux:table.column>
                <flux:table.column align="end">Wartość netto</flux:table.column>
                <flux:table.column align="end">VAT</flux:table.column>
                <flux:table.column align="end">Brutto</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($invoice->lines as $line)
                    <flux:table.row>
                        <flux:table.cell>{{ $line->position }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $line->name }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ rtrim(rtrim(number_format((float) $line->quantity, 4, ',', ' '), '0'), ',') }} {{ $line->unit }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::money($line->unit_price_net) }} zł</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::money($line->net) }} zł</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::money($line->vat) }} zł ({{ (int) $line->vat_rate }}%)</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::money($line->gross) }} zł</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-4 flex justify-end">
            <dl class="w-64 divide-y divide-zinc-100 text-sm dark:divide-white/10">
                <x-detail label="Razem netto">{{ \App\Support\Format::money($invoice->total_net) }} zł</x-detail>
                <x-detail label="VAT {{ (int) $invoice->vat_rate }}%">{{ \App\Support\Format::money($invoice->total_vat) }} zł</x-detail>
                <x-detail label="Do zapłaty" class="text-base font-semibold">{{ \App\Support\Format::money($invoice->total_gross) }} zł</x-detail>
            </dl>
        </div>
    </flux:card>
@endsection
