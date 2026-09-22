@extends('layouts.app')

@section('title', 'Pulpit')
@section('heading', 'Pulpit')
@section('subheading', 'Stan nieruchomości, odczytów i ostatnich rozliczeń')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Nieruchomości', $propertyCount, route('properties.index'), 'building-office-2'],
            ['Lokale', $unitCount, route('units.index'), 'home-modern'],
            ['Aktywni najemcy', $tenantCount, route('tenants.index'), 'users'],
            ['Aktywne liczniki', $meterCount, route('meters.index'), 'cpu-chip'],
        ] as [$label, $value, $url, $icon])
            <a href="{{ $url }}" class="group">
                <flux:card class="transition group-hover:border-zinc-300 dark:group-hover:border-zinc-500">
                    <div class="flex items-center justify-between">
                        <flux:subheading>{{ $label }}</flux:subheading>
                        <flux:icon :name="$icon" class="size-5 text-zinc-400" />
                    </div>
                    <flux:heading size="xl" class="mt-2 tabular-nums">{{ $value }}</flux:heading>
                </flux:card>
            </a>
        @endforeach
    </div>

    @if ($orphanedCount > 0)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="Nieprzypisane odczyty: {{ $orphanedCount }}">
            <flux:callout.text>
                Numer seryjny licznika w odczycie nie pasuje do żadnego licznika w systemie.
                <flux:callout.link :href="route('readings.index', ['orphaned' => 1])">Pokaż listę</flux:callout.link>
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($overdueRentCount > 0)
        <flux:callout variant="warning" icon="banknotes" heading="Niezapłacony czynsz: {{ $overdueRentCount }} nalicz. na {{ \App\Support\Format::money($overdueRent) }} zł">
            <flux:callout.text>
                <flux:callout.link :href="route('rent-charges.index')">Przejdź do czynszów</flux:callout.link>
            </flux:callout.text>
        </flux:callout>
    @endif

    @foreach ($staleStates as $state)
        <flux:callout variant="danger" icon="signal-slash" heading="Synchronizacja {{ $state->source_table }} nie działa">
            <flux:callout.text>
                Ostatni udany przebieg: {{ $state->last_success_at?->diffForHumans() ?? 'nigdy' }}.
                @if ($state->last_error)
                    Ostatni błąd: {{ $state->last_error }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @endforeach

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card>
            <flux:heading size="lg">Ostatnie odczyty</flux:heading>
            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>Licznik</flux:table.column>
                    <flux:table.column align="end">Odczyt</flux:table.column>
                    <flux:table.column>Data</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($latestReadings as $reading)
                        <flux:table.row>
                            <flux:table.cell>
                                @if ($reading->meter)
                                    {{ $reading->meter->name }}
                                @else
                                    <flux:badge size="sm" color="amber">nieprzypisany {{ $reading->source_meter_serial }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $reading->consumption, 2, ',', ' ') }}</flux:table.cell>
                            <flux:table.cell>{{ $reading->measuredAtLabel() }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3">Brak odczytów.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <flux:card>
            <flux:heading size="lg">Ostatnie rozliczenia</flux:heading>
            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>Miesiąc</flux:table.column>
                    <flux:table.column>Lokal</flux:table.column>
                    <flux:table.column align="end">Brutto</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($recentSettlements as $settlement)
                        <flux:table.row>
                            <flux:table.cell>{{ $settlement->month->format('m.Y') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:link :href="route('tenant-settlements.show', $settlement)">{{ $settlement->unit->description }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $settlement->total_gross, 2, ',', ' ') }} zł</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3">Brak rozliczeń.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
@endsection
