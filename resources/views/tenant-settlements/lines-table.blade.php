@php
    $mediaLines = $lines->filter(fn ($line) => data_get($line, 'category') !== \App\Enums\SettlementLineCategory::MaintenanceCost);
    $costLines = $lines->filter(fn ($line) => data_get($line, 'category') === \App\Enums\SettlementLineCategory::MaintenanceCost);
    $money = fn ($value, $decimals = 2) => number_format((float) $value, $decimals, ',', ' ');
    $reading = fn ($value, $line) => \App\Support\Format::reading($value, data_get($line, 'consumption_unit'), true);
@endphp

<flux:card>
    <flux:heading size="lg">Media</flux:heading>
    <flux:table class="mt-2">
        <flux:table.columns>
            <flux:table.column>Pozycja</flux:table.column>
            <flux:table.column align="end">Odczyt początkowy</flux:table.column>
            <flux:table.column align="end">Odczyt końcowy</flux:table.column>
            <flux:table.column align="end">Zużycie</flux:table.column>
            <flux:table.column align="end">Cena jedn.</flux:table.column>
            <flux:table.column align="end">Netto</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($mediaLines as $line)
                <flux:table.row>
                    <flux:table.cell>
                        <div class="font-medium text-zinc-800 dark:text-white">{{ data_get($line, 'label') }}</div>
                        <div class="font-mono text-xs text-zinc-500 dark:text-zinc-400">
                            {{ data_get($line, 'meter_serial') ?: 'brak licznika' }}
                            @if (data_get($line, 'meter_model'))
                                <span class="font-sans">· {{ data_get($line, 'meter_model') }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    @foreach ([['start', $periodStart], ['end', $periodEnd]] as [$side, $boundary])
                        @php
                            $state = data_get($line, $side.'_state');
                            $readingValue = data_get($line, $side.'_reading');
                            $date = data_get($line, $side.'_date');
                        @endphp
                        <flux:table.cell align="end" class="tabular-nums">
                            <div class="font-medium text-zinc-800 dark:text-white">{{ $reading($state, $line) }}</div>
                            <div class="text-xs font-normal text-zinc-500 dark:text-zinc-400">
                                @if (data_get($line, $side.'_interpolated'))
                                    wyliczony na {{ $boundary->format('d.m.Y') }}<br>
                                    z odczytu {{ \App\Support\Format::reading($readingValue, data_get($line, 'consumption_unit'), true) }} z {{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}
                                @elseif ($date)
                                    z odczytu {{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}
                                @else
                                    brak odczytu
                                @endif
                            </div>
                        </flux:table.cell>
                    @endforeach
                    <flux:table.cell align="end" class="tabular-nums">{{ $reading(data_get($line, 'consumption'), $line) }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">
                        <div>{{ \App\Support\Format::price(data_get($line, 'unit_price'), data_get($line, 'consumption_unit')) }} zł</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ data_get($line, 'invoice_number') ?: 'bez faktury' }}</div>
                    </flux:table.cell>
                    <flux:table.cell align="end" variant="strong" class="tabular-nums">{{ $money(data_get($line, 'amount')) }} zł</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">Brak pozycji za media.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</flux:card>

<div class="grid gap-6 lg:grid-cols-3">
    <flux:card class="lg:col-span-2">
        <flux:heading size="lg">Koszty utrzymania nieruchomości</flux:heading>
        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>Pozycja</flux:table.column>
                <flux:table.column align="end">Powierzchnia lokalu</flux:table.column>
                <flux:table.column align="end">Stawka miesięczna</flux:table.column>
                <flux:table.column align="end">Netto</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($costLines as $line)
                    <flux:table.row>
                        <flux:table.cell variant="strong">{{ data_get($line, 'label') }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $money(data_get($line, 'consumption')) }} m²</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $money(data_get($line, 'unit_price'), 4) }} zł/m²</flux:table.cell>
                        <flux:table.cell align="end" variant="strong" class="tabular-nums">{{ $money(data_get($line, 'amount')) }} zł</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">Brak kosztów utrzymania.</flux:table.cell>
                    </flux:table.row>
                @endforelse

                @if ($costLines->isNotEmpty())
                    <flux:table.row>
                        <flux:table.cell colspan="3" variant="strong">Razem koszty utrzymania</flux:table.cell>
                        <flux:table.cell align="end" variant="strong" class="tabular-nums">
                            {{ $money($costLines->sum(fn ($line) => (float) data_get($line, 'amount'))) }} zł
                        </flux:table.cell>
                    </flux:table.row>
                @endif
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card class="space-y-2">
        <flux:heading size="lg">Podsumowanie</flux:heading>
        <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
            <x-detail label="Razem netto" class="tabular-nums">{{ $money($totalNet) }} zł</x-detail>
            <x-detail label="VAT {{ number_format((float) $vatRate * 100, 0) }}%" class="tabular-nums">{{ $money($totalVat) }} zł</x-detail>
        </dl>
        <div class="flex items-baseline justify-between border-t border-zinc-200 pt-4 dark:border-white/20">
            <flux:heading>Razem brutto</flux:heading>
            <flux:heading size="xl" class="tabular-nums">{{ $money($totalGross) }} zł</flux:heading>
        </div>
    </flux:card>
</div>
