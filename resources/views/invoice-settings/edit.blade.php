@extends('layouts.app')

@section('title', 'Ustawienia faktur')
@section('heading', 'Ustawienia faktur')
@section('subheading', 'Dane sprzedawcy i zasady wystawiania faktur za czynsz')

@section('content')
    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('invoice-settings.update') }}" class="space-y-6">
            @csrf

            <div>
                <flux:heading>Sprzedawca</flux:heading>
                <flux:subheading>Dane trafiają na fakturę i do KSeF jako Podmiot1.</flux:subheading>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input name="seller_name" label="Nazwa" :value="old('seller_name', $settings->seller_name)" required />
                <flux:input name="seller_nip" label="NIP" :value="old('seller_nip', $settings->seller_nip)" required />

                <flux:input name="seller_regon" label="REGON" badge="opcjonalnie"
                    description="Pojawia się w stopce faktury."
                    :value="old('seller_regon', $settings->seller_regon)" />
                <flux:input name="seller_address_l1" label="Ulica i numer" placeholder="ul. Przykładowa 1/2"
                    :value="old('seller_address_l1', $settings->seller_address_l1)" required />
                <flux:input name="seller_address_l2" label="Kod pocztowy i miejscowość" placeholder="00-000 Miasto"
                    :value="old('seller_address_l2', $settings->seller_address_l2)" required />
                <flux:input name="seller_phone" label="Telefon" badge="opcjonalnie"
                    :value="old('seller_phone', $settings->seller_phone)" />
                <flux:input name="issue_place" label="Miejsce wystawienia"
                    :value="old('issue_place', $settings->issue_place)" />
            </div>

            <flux:separator variant="subtle" />

            <div>
                <flux:heading>Płatność</flux:heading>
                <flux:subheading>Rachunek pokazywany na fakturze jako właściwy do zapłaty.</flux:subheading>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input name="bank_account" label="Numer rachunku"
                    :value="old('bank_account', $settings->bank_account)" />
                <flux:input name="bank_swift" label="SWIFT" badge="opcjonalnie"
                    :value="old('bank_swift', $settings->bank_swift)" />
                <flux:input name="payment_days" type="number" min="0" max="180" label="Termin płatności (dni)"
                    :value="old('payment_days', $settings->payment_days ?? 7)" required />
            </div>

            <flux:separator variant="subtle" />

            <div>
                <flux:heading>Czynsz</flux:heading>
                <flux:subheading>Jak przeliczać stawkę z kartoteki lokalu na kwoty faktury.</flux:subheading>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input name="vat_rate" type="number" step="0.01" min="0" max="100" label="Stawka VAT (%)"
                    :value="old('vat_rate', $settings->vat_rate ?? 23)" required />
                <flux:input name="line_description" label="Opis pozycji"
                    description="Znaczniki {miesiac} i {rok} zostaną podmienione."
                    :value="old('line_description', $settings->line_description ?? 'Czynsz {miesiac} {rok}')" required />
            </div>

            <x-checkbox name="rent_is_gross" label="Stawka czynszu w kartotece lokalu jest kwotą brutto"
                description="Zaznaczone: 2 214 zł to kwota do zapłaty, a netto (1 800 zł) wyliczamy wstecz. Odznaczone: 2 214 zł to netto, VAT doliczamy."
                :checked="old('rent_is_gross', $settings->rent_is_gross ?? true)" />

            <flux:separator variant="subtle" />

            <div>
                <flux:heading>Rachunki imienne</flux:heading>
                <flux:subheading>
                    Dane wystawcy używane, gdy zamiast faktury wystawiasz rachunek — na przykład
                    w okresie zawieszenia działalności. Rachunek nie idzie do KSeF i nie ma stawki VAT.
                </flux:subheading>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input name="receipt_issuer_name" label="Wystawca (imię i nazwisko)"
                    :value="old('receipt_issuer_name', $settings->receipt_issuer_name)" />

                <flux:input name="receipt_identifier" label="Identyfikator" badge="opcjonalnie"
                    description="Numer NIP albo PESEL, jeśli ma znaleźć się na rachunku."
                    :value="old('receipt_identifier', $settings->receipt_identifier)" />

                <flux:input name="receipt_address_l1" label="Ulica i numer"
                    :value="old('receipt_address_l1', $settings->receipt_address_l1)" />

                <flux:input name="receipt_address_l2" label="Kod pocztowy i miejscowość"
                    :value="old('receipt_address_l2', $settings->receipt_address_l2)" />
            </div>

            <flux:input name="receipt_note" label="Adnotacja na rachunku" badge="opcjonalnie"
                placeholder="np. Zwolnienie z VAT — działalność zawieszona"
                :value="old('receipt_note', $settings->receipt_note)" />

            <x-form-actions :cancel="route('invoices.index')" />
        </form>
    </flux:card>
@endsection
