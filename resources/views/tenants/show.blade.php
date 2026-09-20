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
        <flux:card>
            <flux:heading size="lg">Przypomnienie o płatności</flux:heading>
            <flux:subheading>
                W wiadomości wypisane zostaną wszystkie zaległe miesiące wraz z kwotą łączną.
            </flux:subheading>

            <form method="POST" action="{{ route('tenants.payments.reminder', $tenant) }}" class="mt-3 space-y-4">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input name="email" type="email" label="Adres e-mail" required
                        :description="$tenant->email ? 'Adres z karty najemcy.' : 'Najemca nie ma zapisanego adresu — uzupełnij go w edycji.'"
                        :value="old('email', $tenant->email ?? '')" />

                    <flux:input name="subject" label="Temat" required
                        :value="old('subject', \App\Mail\RentReminderMail::defaultSubject($tenant))" />
                </div>

                <flux:textarea name="body" label="Treść wiadomości" rows="6" required
                    description="Pod treścią automatycznie dopisywana jest tabela zaległości."
                >{{ old('body', \App\Mail\RentReminderMail::defaultBody($tenant, $payments['overdue'])) }}</flux:textarea>

                <flux:button type="submit" variant="primary" icon="paper-airplane">Wyślij przypomnienie</flux:button>
            </form>
        </flux:card>
    @endif

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
                                    {{ $reading->reading_date->format('d.m.Y') }}: {{ number_format((float) $reading->consumption, 2, ',', ' ') }} {{ $meter->type->unit() }}
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
