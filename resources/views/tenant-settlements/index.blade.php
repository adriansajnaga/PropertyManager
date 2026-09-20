@extends('layouts.app')

@section('title', 'Rozliczenia najemców')
@section('heading', 'Rozliczenia najemców')
@section('subheading', 'Miesięczne rozliczenia lokali — media i koszty utrzymania')

@section('content')
    <div class="flex items-center justify-center gap-3">
        <flux:button icon="chevron-left" :href="route('tenant-settlements.index', ['month' => $previousMonth->format('Y-m')])">
            <span class="capitalize">{{ $previousMonth->isoFormat('MMMM YYYY') }}</span>
        </flux:button>

        <flux:heading size="xl" class="min-w-48 text-center capitalize">{{ $month->isoFormat('MMMM YYYY') }}</flux:heading>

        <flux:button icon:trailing="chevron-right" :href="route('tenant-settlements.index', ['month' => $nextMonth->format('Y-m')])">
            <span class="capitalize">{{ $nextMonth->isoFormat('MMMM YYYY') }}</span>
        </flux:button>
    </div>

    @forelse ($properties as $property)
        <flux:card>
            <flux:heading size="lg">{{ $property->name }}</flux:heading>

            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>Lokal</flux:table.column>
                    <flux:table.column>Najemca</flux:table.column>
                    <flux:table.column>Numer rozliczenia</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column align="end">Brutto</flux:table.column>
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($property->units as $unit)
                        @php($settlement = $settlements[$unit->id] ?? null)
                        <flux:table.row>
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('units.show', $unit)">{{ $unit->description }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $unit->tenantAt($month->toDateString())?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $settlement?->number ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($settlement)
                                    <flux:badge size="sm" :color="$settlement->isFinal() ? 'green' : 'yellow'">{{ $settlement->status->label() }}</flux:badge>
                                @elseif (! $unit->settles_utilities)
                                    <flux:badge size="sm" color="zinc">rozlicza się samodzielnie</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">brak</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">
                                {{ $settlement ? number_format((float) $settlement->total_gross, 2, ',', ' ').' zł' : '—' }}
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($settlement)
                                    <flux:button size="sm" variant="ghost" icon="eye" :href="route('tenant-settlements.show', $settlement)" tooltip="Podgląd" />
                                    <flux:button size="sm" variant="ghost" icon="document-arrow-down" :href="route('tenant-settlements.pdf', $settlement)" tooltip="Pobierz PDF" />
                                    @unless ($settlement->isFinal())
                                        <x-edit-button label="Edytuj (zmień rachunki)"
                                            :href="route('tenant-settlements.create', ['unit_id' => $unit->id, 'month' => $month->format('Y-m')])" />
                                        <x-delete-button :action="route('tenant-settlements.destroy', $settlement)" />
                                    @endunless
                                @elseif ($unit->settles_utilities)
                                    <flux:button size="sm" icon="plus"
                                        :href="route('tenant-settlements.create', ['unit_id' => $unit->id, 'month' => $month->format('Y-m')])">
                                        Utwórz
                                    </flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">Brak lokali.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @empty
        <flux:callout icon="information-circle" heading="Brak nieruchomości." />
    @endforelse
@endsection
