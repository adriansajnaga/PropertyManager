@extends('layouts.app')

@section('title', $property->name)
@section('heading', $property->name)
@section('subheading', $property->address)

@section('actions')
    <flux:button icon="pencil-square" :href="route('properties.edit', $property)">Edytuj</flux:button>
    <flux:button variant="primary" icon="plus" :href="route('units.create')">Dodaj lokal</flux:button>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4">
            <flux:heading size="lg">Dane</flux:heading>
            <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
                <x-detail label="Powierzchnia">{{ number_format((float) $property->total_area, 2, ',', ' ') }} m²</x-detail>
                <x-detail label="Lokale">{{ $property->units->count() }}</x-detail>
                <x-detail label="Koszty utrzymania {{ now()->year }}">
                    {{ number_format((float) $property->maintenanceCosts->where('year', now()->year)->sum('annual_cost'), 2, ',', ' ') }} zł
                </x-detail>
            </dl>
            @if ($property->description)
                <flux:text>{{ $property->description }}</flux:text>
            @endif
        </flux:card>

        <div class="space-y-4 lg:col-span-2">
            <flux:heading size="lg">Lokale i najemcy</flux:heading>

            @if ($property->units->isEmpty())
                <flux:text>Brak lokali w tej nieruchomości.</flux:text>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($property->units as $unit)
                        @php($tenant = $tenantsByUnit[$unit->id] ?? null)
                        <a href="{{ route('units.show', $unit) }}" class="group">
                            <flux:card @class([
                                'h-full transition group-hover:border-zinc-300 dark:group-hover:border-zinc-500',
                                'border-dashed' => ! $tenant,
                            ])>
                                <div class="flex items-start justify-between gap-2">
                                    <flux:heading>{{ $unit->description }}</flux:heading>
                                    <flux:badge size="sm" :color="$tenant ? 'green' : 'zinc'">{{ $tenant ? 'wynajęty' : 'wolny' }}</flux:badge>
                                </div>
                                <flux:text class="mt-1 tabular-nums">{{ number_format((float) $unit->area, 2, ',', ' ') }} m²</flux:text>
                                <div class="mt-3 flex items-center gap-2 text-sm">
                                    <flux:icon.user class="size-4 text-zinc-400" />
                                    <span @class(['text-zinc-400' => ! $tenant])>{{ $tenant?->name ?? 'brak najemcy' }}</span>
                                </div>
                            </flux:card>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
