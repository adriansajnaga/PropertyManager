@extends('layouts.app')

@section('title', 'Czynsz')
@section('heading', 'Czynsz')
@section('subheading', 'Naliczany automatycznie za każdy miesiąc najmu — wystaw fakturę i potwierdź zapłatę')

@section('actions')
    <flux:button variant="primary" icon="plus" :href="route('rent-charges.create')">Dodaj czynsz ręcznie</flux:button>
@endsection

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <form method="GET" class="w-40">
            <flux:select name="year" label="Rok" onchange="this.form.submit()">
                @foreach ($years as $option)
                    <flux:select.option :value="$option" :selected="$option === $year">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        </form>

        <div class="flex flex-wrap gap-6 text-sm">
            <div>
                <flux:subheading>Naliczono w {{ $year }}</flux:subheading>
                <flux:heading size="lg" class="tabular-nums">{{ \App\Support\Format::money($chargedTotal) }} zł</flux:heading>
            </div>
            <div>
                <flux:subheading>Zapłacono</flux:subheading>
                <flux:heading size="lg" class="tabular-nums">{{ \App\Support\Format::money($paidTotal) }} zł</flux:heading>
            </div>
            <div>
                <flux:subheading>Pozostało</flux:subheading>
                <flux:heading size="lg" class="tabular-nums">{{ \App\Support\Format::money($chargedTotal - $paidTotal) }} zł</flux:heading>
            </div>
        </div>
    </div>

    @if ($overdueCount > 0)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="Czynsze po terminie: {{ $overdueCount }}" />
    @endif

    @forelse ($chargesByMonth as $month => $charges)
        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="lg" class="capitalize">{{ \Carbon\Carbon::parse($month.'-01')->isoFormat('MMMM YYYY') }}</flux:heading>
                <flux:badge size="sm" :color="$charges->every->isPaid() ? 'green' : 'yellow'">
                    {{ $charges->filter->isPaid()->count() }} z {{ $charges->count() }} zapłacone
                </flux:badge>
            </div>

            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>Lokal</flux:table.column>
                    <flux:table.column>Najemca</flux:table.column>
                    <flux:table.column align="end">Kwota</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Faktura</flux:table.column>
                    <flux:table.column>Termin</flux:table.column>
                    <flux:table.column>Zapłata</flux:table.column>
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($charges as $charge)
                        <flux:table.row>
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('units.show', $charge->unit)">{{ $charge->unit->description }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $charge->tenant?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::money($charge->amount) }} zł</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$charge->status->color()">{{ $charge->status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $charge->invoice_number ?: '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($charge->due_on)
                                    <span @class(['text-red-600 dark:text-red-400' => $charge->isOverdue()])>
                                        {{ $charge->due_on->format('d.m.Y') }}
                                    </span>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($charge->isPaid())
                                    {{ $charge->paid_on->format('d.m.Y') }}
                                @elseif ($charge->isOverdue())
                                    <flux:badge size="sm" color="red">po terminie</flux:badge>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <form method="POST" action="{{ route('rent-charges.paid', $charge) }}" class="inline">
                                    @csrf
                                    <flux:button type="submit" size="sm" variant="ghost"
                                        :icon="$charge->isPaid() ? 'arrow-uturn-left' : 'banknotes'"
                                        :tooltip="$charge->isPaid() ? 'Cofnij zapłatę' : 'Zapłacone dzisiaj'"
                                        :aria-label="$charge->isPaid() ? 'Cofnij zapłatę' : 'Zapłacone dzisiaj'" />
                                </form>
                                <x-edit-button :href="route('rent-charges.edit', $charge)"
                                    :label="$charge->isDraft() ? 'Wpisz fakturę i termin' : 'Edytuj'" />
                                <x-delete-button :action="route('rent-charges.destroy', $charge)" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @empty
        <flux:callout icon="information-circle"
            heading="Brak naliczeń w {{ $year }}.">
            <flux:callout.text>
                Czynsz nalicza się sam lokalom, które mają przypisanego najemcę i wpisaną stawkę
                w <flux:callout.link :href="route('units.index')">kartotece lokalu</flux:callout.link>.
            </flux:callout.text>
        </flux:callout>
    @endforelse
@endsection
