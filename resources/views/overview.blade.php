@extends('layouts.app')

@section('title', 'Pulpit')
@section('heading', 'Pulpit')
@section('subheading', 'Stan nieruchomości, odczytów i ostatnich rozliczeń')

@section('content')
    {{-- Każdy kafelek ma własną barwę, żeby dało się je rozpoznać kątem oka. --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Nieruchomości', $propertyCount, route('properties.index'), 'building-office-2',
                'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',
                'group-hover:border-indigo-300 dark:group-hover:border-indigo-500/60'],
            ['Lokale', $unitCount, route('units.index'), 'home-modern',
                'bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400',
                'group-hover:border-sky-300 dark:group-hover:border-sky-500/60'],
            ['Aktywni najemcy', $tenantCount, route('tenants.index'), 'users',
                'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
                'group-hover:border-emerald-300 dark:group-hover:border-emerald-500/60'],
            ['Aktywne liczniki', $meterCount, route('meters.index'), 'cpu-chip',
                'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
                'group-hover:border-amber-300 dark:group-hover:border-amber-500/60'],
        ] as [$label, $value, $url, $icon, $chip, $hover])
            <a href="{{ $url }}" class="group">
                <flux:card class="transition {{ $hover }}">
                    <div class="flex items-center justify-between">
                        <flux:subheading>{{ $label }}</flux:subheading>
                        <span class="flex size-9 items-center justify-center rounded-lg {{ $chip }}">
                            <flux:icon :name="$icon" class="size-5" />
                        </span>
                    </div>
                    <flux:heading size="xl" class="mt-2 tabular-nums">{{ $value }}</flux:heading>
                </flux:card>
            </a>
        @endforeach
    </div>

    @if ($rentThisMonth['count'] > 0)
        <flux:card>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <flux:subheading>Czynsz za {{ now()->isoFormat('MMMM YYYY') }}</flux:subheading>
                    <flux:heading size="xl" class="mt-1 tabular-nums">
                        <span class="text-emerald-600 dark:text-emerald-400">{{ \App\Support\Format::money($rentThisMonth['paid']) }} zł</span>
                        <span class="text-zinc-400 dark:text-zinc-500">z {{ \App\Support\Format::money($rentThisMonth['charged']) }} zł</span>
                    </flux:heading>
                </div>
                <div class="text-right">
                    <flux:heading size="lg" class="tabular-nums">{{ $rentThisMonth['percent'] }}%</flux:heading>
                    <flux:subheading>{{ $rentThisMonth['paidCount'] }} z {{ $rentThisMonth['count'] }} opłaconych</flux:subheading>
                </div>
            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/10">
                <div class="h-full rounded-full bg-emerald-500 transition-all dark:bg-emerald-400"
                    style="width: {{ max(2, min(100, $rentThisMonth['percent'])) }}%"></div>
            </div>
        </flux:card>
    @endif

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
                                {{-- Kropka medium: woda, prąd i ciepło mają stałe barwy w całej aplikacji. --}}
                                <span class="flex items-center gap-2">
                                    @if ($type = $reading->meter?->type)
                                        <span class="size-2 shrink-0 rounded-full {{ [
                                            'amber' => 'bg-amber-500',
                                            'rose' => 'bg-rose-500',
                                            'sky' => 'bg-sky-500',
                                        ][$type->color()] }}" title="{{ $type->label() }}"></span>
                                    @endif

                                    @if ($reading->meter)
                                        {{ $reading->meter->name }}
                                    @else
                                        <flux:badge size="sm" color="amber">nieprzypisany {{ $reading->source_meter_serial }}</flux:badge>
                                    @endif
                                </span>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::reading($reading->consumption, $reading->unit(), true) }}</flux:table.cell>
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
                    <flux:table.column>Stan</flux:table.column>
                    <flux:table.column align="end">Brutto</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($recentSettlements as $settlement)
                        <flux:table.row>
                            <flux:table.cell>{{ $settlement->month->format('m.Y') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:link :href="route('tenant-settlements.show', $settlement)">{{ $settlement->unit->description }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$settlement->status === \App\Enums\SettlementStatus::Final ? 'green' : 'zinc'">
                                    {{ $settlement->status->label() }}
                                </flux:badge>
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
    </div>
@endsection
