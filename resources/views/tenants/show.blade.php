@extends('layouts.app')

@section('title', $tenant->name)
@section('heading', $tenant->name)
@section('subheading', $tenant->fullAddress() ?: 'Karta najemcy')

@section('actions')
    <flux:button icon="pencil-square" :href="route('tenants.edit', $tenant)">Edytuj</flux:button>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:heading size="lg">Dane</flux:heading>
            <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
                <x-detail label="NIP">{{ $tenant->nip ?: '—' }}</x-detail>
                <x-detail label="E-mail">
                    @if ($tenant->email)
                        <flux:link href="mailto:{{ $tenant->email }}">{{ $tenant->email }}</flux:link>
                    @else
                        <flux:badge size="sm" color="amber">brak adresu</flux:badge>
                    @endif
                </x-detail>
                <x-detail label="Telefon">{{ $tenant->phone ?: '—' }}</x-detail>
                <x-detail label="Status">
                    <flux:badge size="sm" :color="$tenant->is_active ? 'green' : 'zinc'">{{ $tenant->is_active ? 'aktywny' : 'nieaktywny' }}</flux:badge>
                </x-detail>
            </dl>
        </flux:card>

        <flux:card class="lg:col-span-2">
            <flux:heading size="lg">Wynajmowane lokale</flux:heading>
            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>Lokal</flux:table.column>
                    <flux:table.column>Nieruchomość</flux:table.column>
                    <flux:table.column align="end">Powierzchnia</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($units as $unit)
                        <flux:table.row>
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('units.show', $unit)">{{ $unit->description }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $unit->property->name }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $unit->area, 2, ',', ' ') }} m²</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3">Brak przypisanych lokali.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    @include('tenants.payments')

    @if ($payments['overdue']->isNotEmpty())
        @php
            $defaultChannels = array_values(array_filter(['email', $smsReady && $tenant->phone ? 'sms' : null]));
            $channels = (array) old('channels', $defaultChannels);
            $smsText = old('sms_message', \App\Support\RentReminderSms::defaultText($payments['overdue']));
        @endphp

        <flux:card>
            <flux:heading size="lg">Przypomnienie o płatności</flux:heading>
            <flux:subheading>Zaznacz kanały — e-mail i SMS wyjdą jednym kliknięciem.</flux:subheading>

            {{-- Licznik SMS liczy znaki po zamianie polskich liter na łacińskie — tak jak robi to bramka. --}}
            <form method="POST" action="{{ route('tenants.payments.reminder', $tenant) }}" class="mt-4 space-y-5"
                x-data="{
                    email: @js(in_array('email', $channels)),
                    sms: @js(in_array('sms', $channels)),
                    text: @js($smsText),
                    smsInfo(value) {
                        const plain = value.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ł/g, 'l').replace(/Ł/g, 'L');
                        const parts = plain.length <= 160 ? 1 : Math.ceil(plain.length / 153);
                        return plain.length + ' znaków · ' + parts + ' SMS';
                    },
                }">
                @csrf

                <div class="flex flex-wrap gap-8">
                    <x-checkbox name="channels[]" value="email" label="E-mail" x-model="email"
                        :checked="in_array('email', $channels)" />
                    <x-checkbox name="channels[]" value="sms" label="SMS" x-model="sms"
                        :checked="in_array('sms', $channels)"
                        :disabled="! $smsReady"
                        :description="$smsReady ? null : 'Bramka SMS nie jest skonfigurowana — brak SMSAPI_TOKEN w pliku .env.'" />
                </div>

                <div x-show="email" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input name="email" type="email" label="Adres e-mail"
                            :description="$tenant->email ? 'Adres z karty najemcy.' : 'Najemca nie ma zapisanego adresu — uzupełnij go w edycji.'"
                            :value="old('email', $tenant->email ?? '')" />

                        <flux:input name="subject" label="Temat"
                            :value="old('subject', \App\Mail\RentReminderMail::defaultSubject($tenant))" />
                    </div>

                    <flux:textarea name="body" label="Treść wiadomości" rows="6"
                        description="Pod treścią automatycznie dopisywana jest tabela zaległości."
                    >{{ old('body', \App\Mail\RentReminderMail::defaultBody($tenant, $payments['overdue'])) }}</flux:textarea>
                </div>

                <div x-show="sms" x-cloak class="space-y-4" x-on:input="if ($event.target.name === 'sms_message') text = $event.target.value">
                    @if ($smsReady && $smsTestMode)
                        <flux:callout variant="warning" icon="beaker"
                            heading="Tryb testowy bramki SMS — wiadomości są sprawdzane, ale nie wysyłane.">
                            <flux:callout.text>Aby wysyłać naprawdę, ustaw w pliku .env <code>SMSAPI_TEST=false</code>.</flux:callout.text>
                        </flux:callout>
                    @endif
                    <div class="max-w-xs">
                        <flux:input name="phone" label="Numer telefonu"
                            :description="$tenant->phone ? 'Numer z karty najemcy.' : 'Najemca nie ma zapisanego numeru — uzupełnij go w edycji.'"
                            :value="old('phone', $tenant->phone ?? '')" />
                    </div>

                    <div>
                        <flux:textarea name="sms_message" label="Treść SMS" rows="3">{{ $smsText }}</flux:textarea>
                        <flux:text class="mt-1 text-xs tabular-nums" x-text="smsInfo(text)"></flux:text>
                    </div>
                </div>

                <flux:button type="submit" variant="primary" icon="paper-airplane">Wyślij przypomnienie</flux:button>
            </form>
        </flux:card>
    @endif

    <flux:card>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="lg">Faktury</flux:heading>
            <flux:button size="sm" variant="ghost" icon="arrow-right" :href="route('invoices.index')">Wszystkie faktury</flux:button>
        </div>

        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>Numer</flux:table.column>
                <flux:table.column>Wystawiono</flux:table.column>
                <flux:table.column>Termin</flux:table.column>
                <flux:table.column align="end">Netto</flux:table.column>
                <flux:table.column align="end">Brutto</flux:table.column>
                <flux:table.column>KSeF</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($invoices as $invoice)
                    <flux:table.row>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('invoices.show', $invoice)">{{ $invoice->number }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $invoice->issued_on->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->due_on->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::money($invoice->total_net) }} zł</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::money($invoice->total_gross) }} zł</flux:table.cell>
                        <flux:table.cell>
                            @if ($invoice->isInKsef())
                                <span class="font-mono text-xs">{{ $invoice->ksef_number }}</span>
                            @else
                                <flux:badge size="sm" :color="$invoice->status->color()">{{ $invoice->status->label() }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">Brak faktur.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card>
        <flux:heading size="lg">Miesięczne rozliczenia</flux:heading>
        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>Miesiąc</flux:table.column>
                <flux:table.column>Numer</flux:table.column>
                <flux:table.column>Lokal</flux:table.column>
                <flux:table.column align="end">Netto</flux:table.column>
                <flux:table.column align="end">Brutto</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($settlements as $settlement)
                    <flux:table.row>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('tenant-settlements.show', $settlement)">{{ $settlement->month->format('m.Y') }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $settlement->number }}</flux:table.cell>
                        <flux:table.cell>{{ $settlement->unit->description }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $settlement->total_net, 2, ',', ' ') }} zł</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $settlement->total_gross, 2, ',', ' ') }} zł</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="document-arrow-down" :href="route('tenant-settlements.pdf', $settlement)" tooltip="Pobierz PDF" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">Brak rozliczeń.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
    <flux:card>
        <flux:heading size="lg">Liczniki i odczyty z ostatnich 2 miesięcy</flux:heading>

        <div class="mt-4 divide-y divide-zinc-100 dark:divide-white/10">
            @forelse ($meters as $meter)
                @php($readings = $readingsByMeter[$meter->id] ?? collect())
                <div class="py-4 first:pt-0 last:pb-0">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <flux:link :href="route('meters.show', $meter)" class="font-medium">{{ $meter->name }}</flux:link>
                        <flux:badge size="sm">{{ $meter->type->label() }}</flux:badge>
                        <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $meter->serial_number }}</span>
                    </div>

                    @if ($readings->isEmpty())
                        <flux:text>Brak odczytów w tym okresie.</flux:text>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach ($readings->take(20) as $reading)
                                <flux:badge size="sm" color="zinc" class="tabular-nums">
                                    {{ $reading->measuredAtLabel() }}: {{ number_format((float) $reading->consumption, 2, ',', ' ') }} {{ $meter->type->unit() }}
                                </flux:badge>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <flux:text>Brak liczników przypisanych do lokali tego najemcy.</flux:text>
            @endforelse
        </div>
    </flux:card>
@endsection
