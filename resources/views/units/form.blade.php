@csrf

<div class="grid gap-6 sm:grid-cols-2">
    <flux:select name="property_id" label="Nieruchomość" placeholder="— wybierz —" required>
        @foreach ($properties as $property)
            <flux:select.option :value="$property->id" :selected="old('property_id', $unit->property_id) == $property->id">
                {{ $property->name }}
            </flux:select.option>
        @endforeach
    </flux:select>

    <flux:input name="description" label="Opis lokalu" :value="old('description', $unit->description)" required />

    <flux:input name="area" type="number" step="0.01" min="0.01" label="Powierzchnia (m²)" :value="old('area', $unit->area)" required />

    <flux:select name="tenant_id" label="Najemca">
        <flux:select.option value="">— lokal wolny —</flux:select.option>
        @foreach ($tenants as $tenant)
            <flux:select.option :value="$tenant->id" :selected="old('tenant_id', $currentTenantId) == $tenant->id">
                {{ $tenant->name }}
            </flux:select.option>
        @endforeach
    </flux:select>
</div>

<flux:separator variant="subtle" />

<div>
    <flux:heading>Czynsz i kaucja</flux:heading>
    <flux:subheading>Po wpisaniu stawki czynsz nalicza się sam za każdy miesiąc, w którym lokal ma najemcę.</flux:subheading>
</div>

<div class="grid gap-6 sm:grid-cols-3">
    <flux:input name="rent_amount" type="number" step="0.01" min="0" label="Czynsz miesięczny (zł)"
        :value="old('rent_amount', $unit->rent_amount)" />

    <flux:input name="deposit_amount" type="number" step="0.01" min="0" label="Kaucja jednorazowa (zł)"
        :value="old('deposit_amount', $unit->deposit_amount)" />

    <flux:input name="deposit_paid_on" type="date" label="Kaucja wpłacona dnia"
        :value="old('deposit_paid_on', $unit->deposit_paid_on?->format('Y-m-d'))" />
</div>

<flux:separator variant="subtle" />

<div>
    <flux:heading>Rozliczanie mediów</flux:heading>
    <flux:subheading>
        Zaznacz, jeśli lokal ma własne liczniki u dostawców i rozlicza wodę, prąd oraz ciepło samodzielnie.
        Takiemu lokalowi nie utworzysz rozliczenia mediów — czynsz naliczany jest normalnie.
    </flux:subheading>
</div>

<x-checkbox name="skip_utilities" label="Nie rozliczaj mediów"
    :checked="old('skip_utilities', $unit->exists ? ! $unit->settles_utilities : false)" />

<flux:separator variant="subtle" />

<div>
    <flux:heading>Przypisane liczniki</flux:heading>
    <flux:subheading>Podliczniki, z których odczytów liczone jest zużycie lokalu.</flux:subheading>
</div>

<div class="grid gap-6 sm:grid-cols-3">
    @foreach (\App\Enums\MeterType::cases() as $type)
        <flux:select :name="'meters['.$type->value.']'" :label="$type->label()">
            <flux:select.option value="">— brak —</flux:select.option>
            @foreach ($metersByType[$type->value] as $meter)
                <flux:select.option :value="$meter->id" :selected="old('meters.'.$type->value, $currentMeterIds[$type->value]) == $meter->id">
                    {{ $meter->name }} ({{ $meter->serial_number }})
                </flux:select.option>
            @endforeach
        </flux:select>
    @endforeach
</div>

<div class="max-w-xs">
    <flux:input name="valid_from" type="date" label="Przypisania obowiązują od"
        :value="old('valid_from', now()->startOfMonth()->toDateString())" />
</div>

<flux:text>
    Zmiana najemcy lub licznika zamyka poprzednie przypisanie tą datą — rozliczenia z przeszłości pozostają nienaruszone.
</flux:text>

<x-form-actions :cancel="route('units.index')" />
