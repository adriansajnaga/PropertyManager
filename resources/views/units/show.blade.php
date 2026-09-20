@extends('layouts.app')

@section('title', $unit->description)
@section('heading', $unit->description)
@section('subheading', $unit->property->name)

@section('actions')
    <flux:button icon="pencil-square" :href="route('units.edit', $unit)">Edytuj</flux:button>
    @if ($unit->settles_utilities)
        <flux:button variant="primary" icon="calculator"
            :href="route('tenant-settlements.create', ['unit_id' => $unit->id, 'month' => now()->subMonth()->format('Y-m')])">
            Utwórz rozliczenie
        </flux:button>
    @endif
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:heading size="lg">Dane lokalu</flux:heading>
            <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
                <x-detail label="Nieruchomość">
                    <flux:link :href="route('properties.show', $unit->property)">{{ $unit->property->name }}</flux:link>
                </x-detail>
                <x-detail label="Powierzchnia">{{ number_format((float) $unit->area, 2, ',', ' ') }} m²</x-detail>
                <x-detail label="Czynsz miesięczny">
                    {{ $unit->rent_amount ? \App\Support\Format::money($unit->rent_amount).' zł' : '—' }}
                </x-detail>
                <x-detail label="Kaucja">
                    @if ($unit->deposit_amount)
                        {{ \App\Support\Format::money($unit->deposit_amount) }} zł
                        @if ($unit->depositPaid())
                            <flux:badge size="sm" color="green" icon="check">wpłacona {{ $unit->deposit_paid_on->format('d.m.Y') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">brak wpłaty</flux:badge>
                        @endif
                    @else
                        —
                    @endif
                </x-detail>
                <x-detail label="Media">
                    @if ($unit->settles_utilities)
                        rozliczane w aplikacji
                    @else
                        <flux:badge size="sm" color="zinc">lokal rozlicza się samodzielnie</flux:badge>
                    @endif
                </x-detail>
                <x-detail label="Najemca">
                    @if ($tenant)
                        <flux:link :href="route('tenants.show', $tenant)">{{ $tenant->name }}</flux:link>
                    @else
                        <flux:badge size="sm" color="zinc">lokal wolny</flux:badge>
                    @endif
                </x-detail>
            </dl>
        </flux:card>

        <flux:card class="lg:col-span-2">
            <flux:heading size="lg">Przypisane liczniki</flux:heading>
            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>Typ</flux:table.column>
                    <flux:table.column>Nazwa</flux:table.column>
                    <flux:table.column>Numer seryjny</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($meters as $meter)
                        <flux:table.row>
                            <flux:table.cell>{{ $meter->type->label() }}</flux:table.cell>
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('meters.show', $meter)">{{ $meter->name }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $meter->serial_number }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3">Brak przypisanych liczników.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="lg">Rozliczenia</flux:heading>
        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>Miesiąc</flux:table.column>
                <flux:table.column>Numer</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Brutto</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($settlements as $settlement)
                    <flux:table.row>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('tenant-settlements.show', $settlement)">{{ $settlement->month->format('m.Y') }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $settlement->number }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$settlement->isFinal() ? 'green' : 'yellow'">{{ $settlement->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $settlement->total_gross, 2, ',', ' ') }} zł</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">Brak rozliczeń.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card>
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">Czynsz</flux:heading>
                <flux:subheading>Naliczany automatycznie za każdy miesiąc najmu.</flux:subheading>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button size="sm" icon="plus"
                    :href="route('rent-charges.create', ['unit_id' => $unit->id, 'month' => now()->format('Y-m')])"
                    :tooltip="$unit->rent_amount ? 'Kwota zostanie podpowiedziana z kartoteki lokalu' : 'Lokal nie ma jeszcze stawki czynszu'">
                    Dodaj czynsz ręcznie
                </flux:button>
                <flux:button size="sm" variant="ghost" icon="arrow-right" :href="route('rent-charges.index')">Wszystkie czynsze</flux:button>
            </div>
        </div>

        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>Miesiąc</flux:table.column>
                <flux:table.column align="end">Kwota</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Faktura</flux:table.column>
                <flux:table.column>Zapłata</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rentCharges as $charge)
                    <flux:table.row>
                        <flux:table.cell variant="strong">{{ $charge->month->format('m.Y') }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::money($charge->amount) }} zł</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$charge->status->color()">{{ $charge->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $charge->invoice_number ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($charge->isPaid())
                                {{ $charge->paid_on->format('d.m.Y') }}
                            @elseif ($charge->isOverdue())
                                <flux:badge size="sm" color="red">po terminie</flux:badge>
                            @else
                                {{ $charge->due_on ? 'termin '.$charge->due_on->format('d.m.Y') : '—' }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <x-edit-button :href="route('rent-charges.edit', $charge)" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            Brak naliczeń — czynsz pojawi się, gdy lokal ma najemcę i wpisaną stawkę.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card>
        <flux:heading size="lg">Dokumenty</flux:heading>
        <flux:subheading>Umowa najmu, protokoły, korespondencja — pliki widoczne tylko po zalogowaniu.</flux:subheading>

        <form method="POST" action="{{ route('units.documents.store', $unit) }}" enctype="multipart/form-data"
            class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <flux:input type="file" name="file" label="Plik" class="max-w-xs" required
                accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.doc,.docx,.odt,.xls,.xlsx,.ods,.txt" />
            <flux:input name="title" label="Nazwa" placeholder="np. Umowa najmu" class="max-w-xs" />
            <flux:button type="submit" icon="arrow-up-tray">Dodaj plik</flux:button>
        </form>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>Nazwa</flux:table.column>
                <flux:table.column>Plik</flux:table.column>
                <flux:table.column align="end">Rozmiar</flux:table.column>
                <flux:table.column>Dodano</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($documents as $document)
                    <flux:table.row>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('unit-documents.download', $document)">{{ $document->title }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500">{{ $document->original_name }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $document->sizeForHumans() }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $document->created_at->format('d.m.Y') }}
                            @if ($document->uploader)
                                <span class="text-xs text-zinc-500">({{ $document->uploader->name }})</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray"
                                :href="route('unit-documents.download', $document)" tooltip="Pobierz" aria-label="Pobierz" />
                            <x-delete-button :action="route('unit-documents.destroy', $document)"
                                confirm="Na pewno usunąć ten plik? Zostanie skasowany z dysku." />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">Brak plików.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
@endsection
