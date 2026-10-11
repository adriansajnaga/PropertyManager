@extends('layouts.app')

@section('title', 'Odczyty')
@section('heading', 'Odczyty')
@section('subheading', 'Odczyty z synchronizacji i wprowadzone ręcznie')

@section('actions')
    <flux:button variant="primary" icon="plus" :href="route('readings.create')">Dodaj odczyt ręcznie</flux:button>
@endsection

@section('content')
    @if ($linkedNow > 0)
        <flux:callout variant="success" icon="link"
            heading="Przypisano odczyty po numerze seryjnym: {{ $linkedNow }}" />
    @endif
    <div class="flex flex-wrap items-center gap-2">
        <flux:button size="sm" :variant="! $activeType && ! $orphanedOnly ? 'primary' : 'outline'" :href="route('readings.index')">Wszystkie</flux:button>
        @foreach ($types as $type)
            <flux:button size="sm" :variant="$activeType === $type->value ? 'primary' : 'outline'" :href="route('readings.index', ['type' => $type->value])">
                {{ $type->label() }}
            </flux:button>
        @endforeach
        <flux:button size="sm" icon="exclamation-triangle" :variant="$orphanedOnly ? 'primary' : 'outline'" :href="route('readings.index', ['orphaned' => 1])">
            Nieprzypisane ({{ $orphanedCount }})
        </flux:button>
    </div>

    <flux:card>
        <flux:table :paginate="$readings">
            <flux:table.columns>
                <flux:table.column>Data</flux:table.column>
                <flux:table.column>Licznik</flux:table.column>
                <flux:table.column>Numer seryjny</flux:table.column>
                <flux:table.column align="end">Odczyt</flux:table.column>
                <flux:table.column>Źródło</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($readings as $reading)
                    <flux:table.row>
                        <flux:table.cell>
                            <span @if ($reading->recordedAtLabel()) title="Zapisano na serwerze: {{ $reading->recordedAtLabel() }}" @endif>
                                {{ $reading->measuredAtLabel() }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell variant="strong">
                            @if ($reading->meter)
                                <flux:link :href="route('meters.show', $reading->meter)">{{ $reading->meter->name }}</flux:link>
                            @else
                                <flux:badge size="sm" color="amber">nieprzypisany</flux:badge>
                                <span class="ms-1 text-zinc-500 dark:text-zinc-400">{{ $reading->source_meter_name }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $reading->meter?->serial_number ?? $reading->source_meter_serial }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::reading($reading->consumption, $reading->unit(), true) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$reading->isManual() ? 'sky' : ($reading->isCalculated() ? 'purple' : 'zinc')">
                                {{ $reading->sourceLabel() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <x-edit-button :href="route('readings.edit', $reading)" />
                            <x-delete-button :action="route('readings.destroy', $reading)" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">Brak odczytów.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if (auth()->user()?->isAdmin())
        <flux:card>
            <flux:heading size="lg">Kontrola odczytów</flux:heading>
            <flux:subheading>
                Otwarcie tego adresu sprawdza, czy wszystkie czynne liczniki przysłały dziś odczyt.
                Gdy komplet się zgadza, nic się nie dzieje; gdy czegoś brakuje, na adresy
                administratorów idzie e-mail z listą milczących liczników. Ta sama lista braków
                nie przychodzi dwa razy tego samego dnia.
            </flux:subheading>

            <flux:input class="mt-4 font-mono text-xs" readonly :value="$monitorUrl"
                label="Adres do wywołania z crona" />

            <flux:text class="mt-3">
                W cPanelu dodaj zadanie cron z poleceniem
                <code class="rounded bg-zinc-100 px-1 py-0.5 text-xs dark:bg-white/10">curl -s "ADRES" &gt; /dev/null</code>
                — na przykład raz dziennie wieczorem, po godzinie ostatniego odczytu.
                Podpis w adresie jest wyliczony z klucza aplikacji: zmiana APP_KEY unieważni link.
            </flux:text>
        </flux:card>
    @endif
@endsection
