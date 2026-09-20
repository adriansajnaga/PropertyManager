@extends('layouts.app')

@section('title', 'Ustawienia poczty')
@section('heading', 'Ustawienia poczty')
@section('subheading', 'Serwer SMTP używany do wysyłki rozliczeń e-mailem')

@section('content')
    @unless ($settings->isConfigured())
        <flux:callout variant="warning" icon="exclamation-triangle" heading="Wysyłka poczty nie jest jeszcze skonfigurowana">
            <flux:callout.text>
                Do czasu uzupełnienia tych danych wiadomości trafiają wyłącznie do pliku
                <span class="font-mono text-xs">storage/logs/laravel.log</span> i nie docierają do odbiorców.
            </flux:callout.text>
        </flux:callout>
    @endunless

    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('mail-settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input name="host" label="Serwer SMTP" placeholder="np. smtp.poczta.onet.pl"
                    :value="old('host', $settings->host)" required />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input name="port" type="number" min="1" max="65535" label="Port"
                        :value="old('port', $settings->port ?? 587)" required />

                    <flux:select name="encryption" label="Szyfrowanie">
                        @foreach (['ssl' => 'SSL (port 465)', 'tls' => 'STARTTLS (port 587)', '' => 'brak'] as $value => $label)
                            <flux:select.option :value="$value" :selected="old('encryption', $settings->encryption) === ($value ?: null)">
                                {{ $label }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:input name="username" label="Login" badge="opcjonalnie" :value="old('username', $settings->username)" />

                <flux:input name="password" type="password" label="Hasło"
                    :placeholder="filled($settings->password) ? 'bez zmian' : ''"
                    description="Zapisywane w bazie w postaci zaszyfrowanej. Puste pole zostawia dotychczasowe hasło." />

                <flux:input name="from_address" type="email" label="Adres nadawcy"
                    :value="old('from_address', $settings->from_address ?? auth()->user()->email)" required />

                <flux:input name="from_name" label="Nazwa nadawcy"
                    :value="old('from_name', $settings->from_name ?? config('pm.report_issuer'))" />
            </div>

            <div class="flex items-center gap-2 pt-2">
                <flux:button type="submit" variant="primary">Zapisz ustawienia</flux:button>
            </div>
        </form>
    </flux:card>

    <flux:card class="max-w-3xl">
        <flux:heading size="lg">Wiadomość testowa</flux:heading>
        <flux:subheading>Sprawdź, czy dane serwera są poprawne, zanim wyślesz rozliczenie najemcy.</flux:subheading>

        <form method="POST" action="{{ route('mail-settings.test') }}" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-72 flex-1">
                <flux:input name="email" type="email" label="Wyślij na adres" required
                    :value="old('email', auth()->user()->email)" />
            </div>
            <flux:button type="submit" icon="paper-airplane">Wyślij test</flux:button>
        </form>
    </flux:card>
@endsection
