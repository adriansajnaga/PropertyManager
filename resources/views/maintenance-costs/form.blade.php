@csrf

<div class="grid gap-6 sm:grid-cols-2">
    <flux:select name="property_id" label="Nieruchomość" placeholder="— wybierz —" required>
        @foreach ($properties as $property)
            <flux:select.option :value="$property->id" :selected="old('property_id', $cost->property_id) == $property->id">
                {{ $property->name }}
            </flux:select.option>
        @endforeach
    </flux:select>

    <flux:input name="year" type="number" min="2000" max="2100" label="Rok" :value="old('year', $cost->year)" required />

    <flux:input name="description" label="Opis kosztu" :value="old('description', $cost->description)" required />

    <flux:input name="annual_cost" type="number" step="0.01" min="0" label="Roczny koszt (zł netto)"
        description="Dzielony przez powierzchnię nieruchomości i przez 12, potem mnożony przez powierzchnię lokalu."
        :value="old('annual_cost', $cost->annual_cost)" required />
</div>

<x-form-actions :cancel="route('maintenance-costs.index')" />
