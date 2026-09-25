@extends('layouts.app')

@section('title', 'Nowa faktura')
@section('heading', 'Wystawienie faktury')
@section('subheading', $charge->tenant?->name.' · czynsz za '.$charge->month->isoFormat('MMMM YYYY'))

@section('content')
    @if ($draft['number_warning'])
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="$draft['number_warning']" />
    @endif

    <flux:card class="max-w-4xl">
        <form method="POST" action="{{ route('invoices.store') }}" class="space-y-6"
            x-data="{
                quantity: {{ old('quantity', 1) }},
                price: {{ old('unit_price_net', $draft['unit_price_net']) }},
                rate: {{ old('vat_rate', $draft['vat_rate']) }},
                get net() { return Math.round(this.quantity * this.price * 100) / 100 },
                get vat() { return Math.round(this.net * this.rate) / 100 },
                get gross() { return Math.round((this.net + this.vat) * 100) / 100 },
                money(value) { return value.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' zł' },
            }">
            @csrf
            <input type="hidden" name="rent_charge_id" value="{{ $draft['rent_charge_id'] }}">

            <div>
                <flux:heading>Nabywca</flux:heading>
                <flux:subheading>
                    {{ $charge->tenant?->name }} · NIP {{ $charge->tenant?->nip ?: 'brak' }} ·
                    {{ $charge->tenant?->fullAddress() ?: 'brak adresu' }}
                </flux:subheading>
                @if (blank($charge->tenant?->nip))
                    <flux:callout variant="warning" icon="exclamation-triangle" class="mt-2"
                        heading="Najemca nie ma numeru NIP — bez niego KSeF odrzuci fakturę." />
                @endif
            </div>

            <flux:separator variant="subtle" />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input name="number" label="Numer faktury"
                    description="Kolejny wolny numer w tym miesiącu, sprawdzony także w KSeF."
                    :value="old('number', $draft['number'])" required />

                <flux:input name="issued_on" type="date" label="Data wystawienia"
                    :value="old('issued_on', $draft['issued_on'])" required />

                <flux:input name="sold_on" type="date" label="Data sprzedaży"
                    :value="old('sold_on', $draft['sold_on'])" required />

                <flux:input name="due_on" type="date" label="Termin płatności"
                    :value="old('due_on', $draft['due_on'])" required />
            </div>

            <flux:separator variant="subtle" />

            <div>
                <flux:heading>Pozycja</flux:heading>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input name="line_name" label="Nazwa" :value="old('line_name', $draft['line_name'])" required />
                <flux:input name="unit" label="Jednostka" :value="old('unit', 'szt.')" required />

                <flux:input name="quantity" type="number" step="0.0001" min="0.0001" label="Ilość"
                    x-model.number="quantity" :value="old('quantity', 1)" required />

                <flux:input name="unit_price_net" type="number" step="0.01" min="0" label="Cena netto"
                    x-model.number="price" :value="old('unit_price_net', $draft['unit_price_net'])" required />

                <flux:input name="vat_rate" type="number" step="0.01" min="0" max="100" label="Stawka VAT (%)"
                    x-model.number="rate" :value="old('vat_rate', $draft['vat_rate'])" required />
            </div>

            <div class="flex justify-end">
                <dl class="w-64 divide-y divide-zinc-100 text-sm dark:divide-white/10">
                    <x-detail label="Razem netto"><span x-text="money(net)"></span></x-detail>
                    <x-detail label="VAT"><span x-text="money(vat)"></span></x-detail>
                    <x-detail label="Do zapłaty" class="text-base font-semibold"><span x-text="money(gross)"></span></x-detail>
                </dl>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <flux:button type="submit" variant="primary" icon="document-plus">Wystaw fakturę</flux:button>
                <flux:button variant="ghost" :href="route('invoices.index')">Anuluj</flux:button>
            </div>

            <flux:text>
                Faktura powstaje najpierw w aplikacji. Wysyłkę do KSeF uruchomisz przyciskiem na jej karcie,
                gdy sprawdzisz dane.
            </flux:text>
        </form>
    </flux:card>
@endsection
