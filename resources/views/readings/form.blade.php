@csrf

<div class="grid gap-8 lg:grid-cols-[1fr_22rem]"
     x-data="{
        history: @js($history),
        units: @js($units),
        currentId: @js($reading->id),
        meterId: @js((string) old('meter_id', $reading->meter_id ?? '')),
        date: @js(old('reading_date', $reading->reading_date?->toDateString() ?? now()->toDateString())),
        value: @js((string) old('consumption', $reading->consumption ?? '')),
        get rows() { return this.history[this.meterId] || [] },
        get others() { return this.rows.filter(r => r.id !== this.currentId) },
        get previous() { return this.others.find(r => r.iso <= this.date) || null },
        get next() { const later = this.others.filter(r => r.iso > this.date); return later.length ? later[later.length - 1] : null },
        get unit() { return this.units[this.meterId] || '' },
        get number() { return this.value === '' ? null : parseFloat(String(this.value).replace(',', '.')) },
        get tooLow() { return this.number !== null && this.previous !== null && this.number < this.previous.value },
        get tooHigh() { return this.number !== null && this.next !== null && this.number > this.next.value },
        fmt(v) { return Number(v).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 4 }) },
     }">

    <div class="space-y-6">
        <flux:select name="meter_id" label="Licznik" placeholder="— wybierz —" x-model="meterId" required>
            @foreach ($meters as $meter)
                <flux:select.option :value="$meter->id" :selected="old('meter_id', $reading->meter_id) == $meter->id">
                    {{ $meter->name }} — {{ $meter->type->label() }} ({{ $meter->serial_number }})
                </flux:select.option>
            @endforeach
        </flux:select>

        <div class="grid items-start gap-6 sm:grid-cols-2">
            <flux:input name="reading_date" type="date" label="Data odczytu" x-model="date" required
                :value="old('reading_date', $reading->reading_date?->toDateString() ?? now()->toDateString())" />

            <div class="space-y-2">
                <flux:input name="consumption" type="number" step="0.0001" min="0" label="Odczyt (stan licznika)"
                    x-model="value" x-bind:min="previous ? previous.value : 0" required
                    :value="old('consumption', $reading->consumption)" />

                <template x-if="previous && number !== null && ! tooLow && ! tooHigh">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Zużycie od poprzedniego odczytu:
                        <span class="font-medium text-zinc-800 tabular-nums dark:text-white" x-text="fmt(number - previous.value) + ' ' + unit"></span>
                    </p>
                </template>

                <template x-if="tooLow">
                    <p class="text-sm font-medium text-red-600 dark:text-red-400">
                        Wartość mniejsza od poprzedniego odczytu
                        (<span class="tabular-nums" x-text="fmt(previous.value) + ' ' + unit"></span> z <span x-text="previous.date"></span>).
                        Stan licznika nie może maleć — sprawdź, czy nie ma pomyłki.
                    </p>
                </template>

                <template x-if="tooHigh">
                    <p class="text-sm font-medium text-red-600 dark:text-red-400">
                        Wartość większa od późniejszego odczytu
                        (<span class="tabular-nums" x-text="fmt(next.value) + ' ' + unit"></span> z <span x-text="next.date"></span>).
                    </p>
                </template>
            </div>
        </div>

        <flux:input name="raw_hex" label="Wartość HEX" badge="opcjonalnie" :value="old('raw_hex', $reading->raw_hex)" />

        <x-form-actions :cancel="route('readings.index')" />
    </div>

    <aside class="h-fit rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-white/10 dark:bg-white/5">
        <flux:heading>Ostatnie odczyty licznika</flux:heading>

        <template x-if="! meterId">
            <flux:text class="mt-2">Wybierz licznik, aby zobaczyć jego ostatnie odczyty.</flux:text>
        </template>

        <template x-if="meterId && rows.length === 0">
            <flux:text class="mt-2">Brak odczytów — to będzie pierwszy odczyt tego licznika.</flux:text>
        </template>

        <template x-if="rows.length > 0">
            <div>
                <table class="mt-3 w-full text-sm">
                    <tbody class="divide-y divide-zinc-200 dark:divide-white/10">
                        <template x-for="row in rows" :key="row.id">
                            <tr :class="previous && row.id === previous.id ? 'font-semibold text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-300'">
                                <td class="py-1.5" x-text="row.date"></td>
                                <td class="py-1.5 text-right tabular-nums" x-text="fmt(row.value) + ' ' + unit"></td>
                                <td class="py-1.5 ps-2 text-right text-xs text-zinc-400"
                                    x-text="row.id === currentId ? 'edytowany' : row.source"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <template x-if="previous">
                    <div class="mt-4 rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-zinc-800">
                        <div class="text-zinc-500 dark:text-zinc-400">Minimalna wartość</div>
                        <div class="font-semibold tabular-nums text-zinc-900 dark:text-white" x-text="fmt(previous.value) + ' ' + unit"></div>
                    </div>
                </template>
            </div>
        </template>
    </aside>
</div>
