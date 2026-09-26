@php
    use App\Enums\DocumentType;

    $isReceipt = $type === DocumentType::Receipt;
    $lines = old('lines', $draft['lines']);
@endphp

@extends('layouts.app')

@section('title', 'Nowy dokument')
@section('heading', 'Wystawienie '.($isReceipt ? 'rachunku' : 'faktury'))
@section('subheading', $charge->tenant?->name.' · czynsz za '.$charge->month->isoFormat('MMMM YYYY'))

@section('content')
    @if ($draft['number_warning'])
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="$draft['number_warning']" />
    @endif

    <flux:card class="max-w-5xl">
        <form method="POST" action="{{ route('invoices.store') }}" class="space-y-6"
            x-data="{
                rate: {{ old('vat_rate', $draft['vat_rate']) }},
                lines: @js(array_values($lines)),
                add() { this.lines.push({ name: '', unit: 'szt.', quantity: 1, unit_price_net: 0 }) },
                remove(index) { if (this.lines.length > 1) this.lines.splice(index, 1) },
                lineNet(line) { return Math.round((line.quantity || 0) * (line.unit_price_net || 0) * 100) / 100 },
                get net() { return Math.round(this.lines.reduce((sum, line) => sum + this.lineNet(line), 0) * 100) / 100 },
                get vat() { return Math.round(this.lines.reduce((sum, line) => sum + this.lineNet(line) * this.rate, 0)) / 100 },
                get gross() { return Math.round((this.net + this.vat) * 100) / 100 },
                money(value) { return (value || 0).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' zł' },
            }">
            @csrf
            <input type="hidden" name="rent_charge_id" value="{{ $draft['rent_charge_id'] }}">
            <input type="hidden" name="document_type" value="{{ $draft['document_type'] }}">

            <div>
                <flux:heading>Nabywca</flux:heading>
                <flux:subheading>
                    {{ $charge->tenant?->name }} · NIP {{ $charge->tenant?->nip ?: 'brak' }} ·
                    {{ $charge->tenant?->fullAddress() ?: 'brak adresu' }}
                </flux:subheading>
                @if (! $isReceipt && blank($charge->tenant?->nip))
                    <flux:callout variant="warning" icon="exclamation-triangle" class="mt-2"
                        heading="Najemca nie ma numeru NIP — bez niego KSeF odrzuci fakturę." />
                @endif
            </div>

            <flux:separator variant="subtle" />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input name="number" :label="($isReceipt ? 'Numer rachunku' : 'Numer faktury')"
                    :description="$isReceipt ? 'Kolejny wolny numer w osobnej serii rachunków.' : 'Kolejny wolny numer w tym miesiącu, sprawdzony także w KSeF.'"
                    :value="old('number', $draft['number'])" required />

                <flux:input name="issued_on" type="date" label="Data wystawienia"
                    :value="old('issued_on', $draft['issued_on'])" required />

                <flux:input name="sold_on" type="date" label="Data sprzedaży"
                    :value="old('sold_on', $draft['sold_on'])" required />

                <flux:input name="due_on" type="date" label="Termin płatności"
                    :value="old('due_on', $draft['due_on'])" required />

                <flux:input name="vat_rate" type="number" step="0.01" min="0" max="100" label="Stawka VAT (%)"
                    :readonly="$isReceipt"
                    :description="$isReceipt ? 'Rachunek jest bez podatku.' : 'Stawka wspólna dla wszystkich pozycji dokumentu.'"
                    x-model.number="rate" :value="old('vat_rate', $draft['vat_rate'])" required />
            </div>

            <flux:separator variant="subtle" />

            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading>Pozycje</flux:heading>
                <flux:button size="sm" variant="ghost" icon="plus" type="button" x-on:click="add()">Dodaj pozycję</flux:button>
            </div>

            <div class="space-y-3">
                <template x-for="(line, index) in lines" :key="index">
                    <div class="grid items-end gap-3 sm:grid-cols-[1fr_5rem_6rem_8rem_7rem_2.5rem]">
                        <flux:input x-bind:name="`lines[${index}][name]`" x-model="line.name" label="Nazwa" required />
                        <flux:input x-bind:name="`lines[${index}][unit]`" x-model="line.unit" label="Jedn." required />
                        <flux:input x-bind:name="`lines[${index}][quantity]`" x-model.number="line.quantity"
                            type="number" step="0.0001" min="0.0001" label="Ilość" required />
                        <flux:input x-bind:name="`lines[${index}][unit_price_net]`" x-model.number="line.unit_price_net"
                            type="number" step="0.01" min="0" label="Cena netto" required />

                        <div>
                            <flux:subheading class="mb-1 text-xs">Wartość</flux:subheading>
                            <div class="py-2 text-sm font-medium tabular-nums" x-text="money(lineNet(line))"></div>
                        </div>

                        <flux:button size="sm" variant="ghost" icon="trash" type="button"
                            x-on:click="remove(index)" x-bind:disabled="lines.length === 1"
                            aria-label="Usuń pozycję" />
                    </div>
                </template>
            </div>

            <div class="flex justify-end">
                <dl class="w-64 divide-y divide-zinc-100 text-sm dark:divide-white/10">
                    <x-detail label="Razem netto"><span x-text="money(net)"></span></x-detail>
                    <x-detail label="VAT"><span x-text="money(vat)"></span></x-detail>
                    <x-detail label="Do zapłaty" class="text-base font-semibold"><span x-text="money(gross)"></span></x-detail>
                </dl>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <flux:button type="submit" variant="primary" icon="document-plus">
                    Wystaw {{ $isReceipt ? 'rachunek' : 'fakturę' }}
                </flux:button>
                <flux:button variant="ghost" :href="route('invoices.index')">Anuluj</flux:button>
            </div>

            @if ($isReceipt)
                <flux:text>
                    Rachunek imienny nie ma stawki VAT i nie jest wysyłany do KSeF — zostaje w aplikacji.
                    Numerowany jest w osobnej serii niż faktury. Do jednego naliczenia czynszu wystawiasz
                    albo fakturę, albo rachunek.
                </flux:text>
            @else
                <flux:text>
                    Faktura powstaje najpierw w aplikacji. Wysyłkę do KSeF uruchomisz przyciskiem na jej karcie,
                    gdy sprawdzisz dane. Do jednego naliczenia czynszu wystawiasz albo fakturę, albo rachunek.
                </flux:text>
            @endif
        </form>
    </flux:card>
@endsection
