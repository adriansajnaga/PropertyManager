@csrf

<div class="grid gap-6 sm:grid-cols-2">
    <flux:select name="type" label="Rodzaj" required>
        @foreach ($types as $type)
            <flux:select.option :value="$type->value" :selected="old('type', $bill->type?->value) === $type->value">
                {{ $type->label() }}
            </flux:select.option>
        @endforeach
    </flux:select>

    <flux:input name="month" type="month" label="Miesiąc" :value="old('month', $bill->month?->format('Y-m'))" required />

    <flux:input name="invoice_number" label="Numer faktury" :value="old('invoice_number', $bill->invoice_number)" required />

    <div class="grid grid-cols-[1fr_7rem] gap-4"
         x-data="{
            base: @js((string) old('base_net_price', $bill->base_net_price ?? '')),
            margin: @js((string) old('margin_percent', $bill->exists ? $bill->margin_percent : config('pm.default_margin_percent'))),
            get effective() {
                const b = parseFloat(String(this.base).replace(',', '.'));
                const m = parseFloat(String(this.margin).replace(',', '.')) || 0;
                return isNaN(b) ? null : b * (1 + m / 100);
            },
         }">
        <flux:input name="base_net_price" type="number" step="0.0001" min="0" label="Cena netto z faktury"
            x-model="base" description="Prąd: zł/kWh, woda: zł/m³." required />

        <flux:input name="margin_percent" type="number" step="0.01" min="0" max="100" label="Marża (%)"
            x-model="margin" />

        <div class="col-span-2 -mt-2">
            <template x-if="effective !== null">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Cena stosowana w rozliczeniach:
                    <span class="font-medium tabular-nums text-zinc-800 dark:text-white"
                          x-text="effective.toLocaleString('pl-PL', { minimumFractionDigits: 4, maximumFractionDigits: 4 }) + ' zł'"></span>
                    <span class="text-xs">— marża nie jest nigdzie pokazywana poza tym formularzem.</span>
                </p>
            </template>
        </div>
    </div>

    <flux:input name="consumption_value" type="number" step="0.0001" min="0" label="Wartość zużycia z faktury"
        badge="opcjonalnie" :value="old('consumption_value', $bill->consumption_value)" />

    <flux:input name="net_amount" type="number" step="0.01" min="0" label="Kwota netto z faktury"
        badge="opcjonalnie" description="Pokazywana na rozliczeniu w wyliczeniu ceny jednostkowej."
        :value="old('net_amount', $bill->net_amount)" />
</div>

<x-form-actions :cancel="route('utility-bills.index')" />
