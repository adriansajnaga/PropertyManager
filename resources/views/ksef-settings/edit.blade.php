@extends('layouts.app')

@section('title', 'KSeF')
@section('heading', 'Krajowy System e-Faktur')
@section('subheading', 'Dostęp do API KSeF 2.0 — wystawianie i pobieranie faktur')

@section('content')
    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('ksef-settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <flux:select name="environment" label="Środowisko" required>
                @foreach ($environments as $environment)
                    <flux:select.option :value="$environment->value"
                        :selected="old('environment', $settings->environment?->value) === $environment->value">
                        {{ $environment->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input name="nip" label="NIP podatnika"
                    description="Kontekst, w którym działamy w KSeF — Twój NIP jako wystawcy faktur."
                    :value="old('nip', $settings->nip)" required />

                <flux:input name="token" type="password" label="Token KSeF"
                    :description="$settings->isConfigured() ? 'Token jest zapisany. Puste pole zostawia dotychczasowy.' : 'Wygenerowany w aplikacji KSeF, uprawnienia InvoiceRead i InvoiceWrite.'"
                    autocomplete="new-password" />
            </div>

            <flux:callout icon="information-circle">
                <flux:callout.text>
                    Token generujesz raz, w aplikacji webowej KSeF, po zalogowaniu podpisem kwalifikowanym
                    albo Profilem Zaufanym. Jest sekretem — trzymamy go w bazie zaszyfrowanego kluczem aplikacji
                    i nigdzie nie pokazujemy ponownie.
                </flux:callout.text>
            </flux:callout>

            <div class="flex items-center gap-2 pt-2">
                <flux:button type="submit" variant="primary">Zapisz</flux:button>
            </div>
        </form>

        <flux:separator variant="subtle" class="my-6" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading>Sprawdzenie połączenia</flux:heading>
                <flux:subheading>
                    @if ($settings->verified_at)
                        Ostatnie udane połączenie: {{ $settings->verified_at->format('d.m.Y, H:i') }}.
                    @else
                        Połączenie nie było jeszcze sprawdzane.
                    @endif
                </flux:subheading>
            </div>

            <form method="POST" action="{{ route('ksef-settings.test') }}">
                @csrf
                <flux:button type="submit" icon="signal">Sprawdź połączenie</flux:button>
            </form>
        </div>
    </flux:card>
@endsection
