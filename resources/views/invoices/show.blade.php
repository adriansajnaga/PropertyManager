@extends('layouts.app')

@section('title', $invoice->title())
@section('heading', $invoice->title())
@section('subheading', 'Wystawiona '.$invoice->issued_on->format('d.m.Y').' · termin płatności '.$invoice->due_on->format('d.m.Y'))

@section('actions')
    <flux:button icon="document-arrow-down" :href="route('invoices.pdf', $invoice)">PDF</flux:button>

    @if ($invoice->goesToKsef() && ! $invoice->isInKsef())
        <form method="POST" action="{{ route('invoices.send', $invoice) }}" class="inline">
            @csrf
            <flux:button type="submit" variant="primary" icon="paper-airplane">Wyślij do KSeF</flux:button>
        </form>
    @endif

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
            <flux:heading size="lg">{{ $invoice->goesToKsef() ? 'KSeF' : 'Rachunek' }}</flux:heading>
            <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
                <x-detail label="Status">
                    <flux:badge size="sm" :color="$invoice->status->color()">{{ $invoice->status->label() }}</flux:badge>
                </x-detail>
                <x-detail label="Numer KSeF" class="font-mono text-xs">{{ $invoice->ksef_number ?: '—' }}</x-detail>
                <x-detail label="Wysłano do KSeF">{{ $invoice->ksef_sent_at?->format('d.m.Y, H:i') ?: '—' }}</x-detail>
                <x-detail label="Wysłano najemcy">
                    @if ($invoice->wasEmailed())
                        {{ $invoice->emailed_at->format('d.m.Y, H:i') }}
                        <span class="block text-xs text-zinc-500">{{ $invoice->emailed_to }}</span>
                    @else
                        —
                    @endif
                </x-detail>
            </dl>

            @if ($verificationUrl)
                <div class="mt-2">
                    <flux:subheading>Weryfikacja w KSeF</flux:subheading>
                    <flux:link :href="$verificationUrl" target="_blank" class="text-xs break-all">
                        {{ $verificationUrl }}
                    </flux:link>
                    <flux:text class="mt-1 text-xs">
                        Ten sam link kryje się w kodzie QR na PDF — otwiera stronę KSeF, która potwierdza,
                        że faktura tam jest i nie została zmieniona.
                    </flux:text>
                </div>
            @endif

            @if ($invoice->ksef_error)
                <flux:callout variant="danger" icon="x-circle" :heading="$invoice->ksef_error" />
            @endif
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="lg">Wyślij {{ $invoice->isReceipt() ? 'rachunek' : 'fakturę' }} najemcy</flux:heading>
        <flux:subheading>PDF trafi do wiadomości jako załącznik.</flux:subheading>

        <form method="POST" action="{{ route('invoices.email', $invoice) }}" class="mt-3 space-y-4">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input name="email" type="email" label="Adres e-mail" required
                    :description="$invoice->tenant?->email ? 'Adres z kartoteki najemcy.' : 'Najemca nie ma zapisanego adresu — uzupełnij go w kartotece.'"
                    :value="old('email', $invoice->tenant?->email ?? auth()->user()->email)" />

                <flux:input name="subject" label="Temat" required
                    :value="old('subject', \App\Mail\InvoiceMail::defaultSubject($invoice))" />
            </div>

            <flux:textarea name="body" label="Treść wiadomości" rows="5" required
                description="Pod treścią automatycznie dopisywane są dane dokumentu i kwota."
            >{{ old('body', \App\Mail\InvoiceMail::defaultBody($invoice)) }}</flux:textarea>

            <flux:button type="submit" variant="primary" icon="paper-airplane">Wyślij</flux:button>
        </form>
    </flux:card>

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
