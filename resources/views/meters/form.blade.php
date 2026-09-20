@csrf

<div class="grid gap-6 sm:grid-cols-2">
    <flux:select name="type" label="Typ" required>
        @foreach ($types as $type)
            <flux:select.option :value="$type->value" :selected="old('type', $meter->type?->value) === $type->value">
                {{ $type->label() }}
            </flux:select.option>
        @endforeach
    </flux:select>

    <flux:input name="serial_number" label="Numer seryjny (METER)"
        description="Musi odpowiadać kolumnie METER w bazie iascomm_reader — po nim dopasowywane są odczyty."
        :value="old('serial_number', $meter->serial_number)" required />

    <flux:input name="name" label="Nazwa"
        :description="'Podlicznik prądu kotłowni musi nazywać się „'.config('pm.boiler_meter_name').'”.'"
        :value="old('name', $meter->name)" required />

    <flux:input name="model" label="Model urządzenia" badge="opcjonalnie" placeholder="np. F&amp;F LE-03M"
        description="Trafia na rozliczenie PDF obok numeru seryjnego."
        :value="old('model', $meter->model)" />
</div>

<div class="space-y-3">
    <x-checkbox name="is_active" label="Licznik aktywny" :checked="old('is_active', $meter->is_active ?? true)" />
    <x-checkbox name="is_main" label="Licznik główny (budynkowy)"
        description="Nie jest sumowany jako podlicznik lokalu przy rozliczeniu ciepła."
        :checked="old('is_main', $meter->is_main ?? false)" />
    <x-checkbox name="is_boiler_supply" label="Podlicznik prądu kotłowni"
        description="Jego zużycie jest podstawą wyliczenia ceny za 1 GJ w rozliczeniu kosztów ciepła."
        :checked="old('is_boiler_supply', $meter->is_boiler_supply ?? false)" />
</div>

<x-form-actions :cancel="route('meters.index')" />
