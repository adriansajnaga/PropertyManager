@csrf

<div class="grid gap-6 sm:grid-cols-2">
    <flux:select name="unit_id" label="Lokal" placeholder="— wybierz —" required>
        @foreach ($units as $unit)
            <flux:select.option :value="$unit->id" :selected="old('unit_id', $charge->unit_id) == $unit->id">
                {{ $unit->property->name }} — {{ $unit->description }}
            </flux:select.option>
        @endforeach
    </flux:select>

    <flux:input name="month" type="month" label="Miesiąc"
        :value="old('month', $charge->month?->format('Y-m'))" required />

    <flux:input name="amount" type="number" step="0.01" min="0" label="Kwota czynszu (zł)"
        description="Domyślnie stawka z kartoteki lokalu."
        :value="old('amount', $charge->amount)" required />

    <flux:input name="invoice_number" label="Numer faktury"
        description="Wpisanie numeru zmienia szkic w czynsz wystawiony."
        :value="old('invoice_number', $charge->invoice_number)" />

    <flux:input name="due_on" type="date" label="Termin płatności"
        :value="old('due_on', $charge->due_on?->format('Y-m-d'))" />
</div>

<flux:separator variant="subtle" />

<div class="space-y-4" x-data="{ paid: @js((bool) old('is_paid', $charge->isPaid())) }">
    <flux:checkbox name="is_paid" value="1" label="Czynsz zapłacony" x-model="paid" />

    <div class="max-w-xs" x-show="paid" x-cloak>
        <flux:input name="paid_on" type="date" label="Data zapłaty"
            description="Puste pole oznacza dzisiejszą datę."
            :value="old('paid_on', $charge->paid_on?->format('Y-m-d'))" />
    </div>
</div>

<flux:input name="note" label="Uwagi" :value="old('note', $charge->note)" />

<x-form-actions :cancel="route('rent-charges.index')" />
