<flux:card>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="lg">Płatności czynszu</flux:heading>
            <flux:subheading>Naliczenia, faktury i wpłaty w wybranym roku.</flux:subheading>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <form method="GET" class="w-32">
                <flux:select name="year" label="Rok" size="sm" onchange="this.form.submit()">
                    @foreach ($payments['years'] as $option)
                        <flux:select.option :value="$option" :selected="$option === $payments['year']">{{ $option }}</flux:select.option>
                    @endforeach
                </flux:select>
            </form>

            <flux:button size="sm" icon="document-arrow-down"
                :href="route('tenants.payments.pdf', ['tenant' => $tenant, 'year' => $payments['year']])">
                Zestawienie roczne PDF
            </flux:button>
        </div>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Naliczono', $payments['charged'], 'zinc'],
            ['Zapłacono', $payments['paid'], 'green'],
            ['Pozostało', $payments['outstanding'], $payments['outstanding'] > 0 ? 'red' : 'green'],
        ] as [$label, $value, $color])
            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <flux:subheading>{{ $label }}</flux:subheading>
                <flux:heading size="lg" class="tabular-nums" @class([
                    'text-red-600 dark:text-red-400' => $color === 'red',
                ])>{{ \App\Support\Format::money($value) }} zł</flux:heading>
            </div>
        @endforeach
    </div>

    @if ($payments['overdue']->isNotEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" class="mt-4"
            heading="Zaległości: {{ $payments['overdue']->count() }} na {{ \App\Support\Format::money($payments['overdue']->sum(fn ($charge) => (float) $charge->amount)) }} zł" />
    @endif

    <flux:table class="mt-4">
        <flux:table.columns>
            <flux:table.column>Miesiąc</flux:table.column>
            <flux:table.column>Lokal</flux:table.column>
            <flux:table.column align="end">Kwota</flux:table.column>
            <flux:table.column>Faktura</flux:table.column>
            <flux:table.column>Termin</flux:table.column>
            <flux:table.column>Zapłata</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($payments['charges'] as $charge)
                <flux:table.row>
                    <flux:table.cell variant="strong" class="capitalize">{{ $charge->month->isoFormat('MMMM') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('units.show', $charge->unit)">{{ $charge->unit->description }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ \App\Support\Format::money($charge->amount) }} zł</flux:table.cell>
                    <flux:table.cell>{{ $charge->invoice_number ?: '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $charge->due_on?->format('d.m.Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($charge->isPaid())
                            <span @class(['text-amber-600 dark:text-amber-400' => $charge->due_on && $charge->paid_on->gt($charge->due_on)])>
                                {{ $charge->paid_on->format('d.m.Y') }}
                            </span>
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($charge->isOverdue())
                            <flux:badge size="sm" color="red">po terminie</flux:badge>
                        @else
                            <flux:badge size="sm" :color="$charge->status->color()">{{ $charge->status->label() }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <x-edit-button :href="route('rent-charges.edit', $charge)" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">Brak naliczeń czynszu w {{ $payments['year'] }}.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($payments['paidLate']->isNotEmpty())
        <flux:text class="mt-3">
            Płatności po terminie w {{ $payments['year'] }}: {{ $payments['paidLate']->count() }}
            z {{ $payments['charges']->filter->isPaid()->count() }} wpłat.
        </flux:text>
    @endif
</flux:card>
