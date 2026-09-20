@csrf

<div class="grid gap-6 sm:grid-cols-2">
    <flux:input name="name" label="Nazwa" :value="old('name', $tenant->name)" required />
    <flux:input name="nip" label="NIP" :value="old('nip', $tenant->nip)" />

    <flux:input name="email" type="email" label="Adres e-mail"
        description="Domyślny odbiorca rozliczeń i przypomnień o płatności."
        :value="old('email', $tenant->email)" />

    <flux:input name="phone" label="Telefon" :value="old('phone', $tenant->phone)" />
    <flux:input name="street" label="Ulica" :value="old('street', $tenant->street)" />

    <div class="grid grid-cols-3 gap-4">
        <flux:input name="zip" label="Kod pocztowy" :value="old('zip', $tenant->zip)" />
        <div class="col-span-2">
            <flux:input name="city" label="Miasto" :value="old('city', $tenant->city)" />
        </div>
    </div>
</div>

<x-checkbox name="is_active" label="Najemca aktywny" :checked="old('is_active', $tenant->is_active ?? true)" />

<x-form-actions :cancel="route('tenants.index')" />
