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

<flux:separator variant="subtle" />

<div x-data="{ module: @js((bool) old('is_module', $meter->is_module ?? false)) }" class="space-y-4">
    <div>
        <flux:heading>Nakładka radiowa</flux:heading>
        <flux:subheading>
            Wodomierze są mechaniczne, a odczyt zdalny robi nakładka (np. Apator AT-WMBUS-16-2).
            Nakładka liczy dalej od stanu, który miała u poprzedniego użytkownika, dlatego trzymamy ją
            jako osobną pozycję i przeliczamy jej odczyty na stan licznika.
        </flux:subheading>
    </div>

    <x-checkbox name="is_module" label="To jest nakładka radiowa, nie licznik" x-model="module"
        :checked="old('is_module', $meter->is_module ?? false)" />

    <div x-show="module" x-cloak class="grid gap-6 sm:grid-cols-2">
        <flux:select name="module_for_meter_id" label="Zamontowana na liczniku" placeholder="— wybierz licznik —">
            @foreach ($hostMeters as $host)
                <flux:select.option :value="$host->id" :selected="old('module_for_meter_id', $meter->module_for_meter_id) == $host->id">
                    {{ $host->name }} — {{ $host->type->label() }} ({{ $host->serial_number }})
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:input name="module_offset" type="number" step="0.0001" label="Różnica wskazań"
            description="Stan licznika = odczyt nakładki + różnica. Gdy nakładka pokazuje więcej niż licznik, wpisz wartość ujemną."
            :value="old('module_offset', $meter->module_offset ?? 0)" />
    </div>
</div>

<x-form-actions :cancel="route('meters.index')" />
