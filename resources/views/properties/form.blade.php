@csrf

<div class="grid gap-6 sm:grid-cols-2">
    <flux:input name="name" label="Nazwa" :value="old('name', $property->name)" required />
    <flux:input name="address" label="Adres" :value="old('address', $property->address)" required />
    <flux:input name="total_area" type="number" step="0.01" min="0.01" label="Powierzchnia do wynajęcia (m²)"
        description="Podstawa proracji rocznych kosztów utrzymania na lokale."
        :value="old('total_area', $property->total_area)" required />
</div>

<flux:textarea name="description" label="Opis" rows="3">{{ old('description', $property->description) }}</flux:textarea>

<x-form-actions :cancel="route('properties.index')" />
