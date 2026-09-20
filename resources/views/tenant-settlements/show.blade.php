@extends('layouts.app')

@section('title', 'Rozliczenie '.$settlement->number)
@section('heading', $settlement->unit->description.' — '.$settlement->month->isoFormat('MMMM YYYY'))
@section('subheading', 'Rozliczenie '.$settlement->number)

@section('actions')
    <flux:button icon="document-arrow-down" :href="route('tenant-settlements.pdf', $settlement)">PDF</flux:button>

    @unless ($settlement->isFinal())
        <flux:button icon="receipt-percent"
            :href="route('tenant-settlements.create', ['unit_id' => $settlement->unit_id, 'month' => $settlement->month->format('Y-m')])">
            Zmień rachunki
        </flux:button>

        <form method="POST" action="{{ route('tenant-settlements.recalculate', $settlement) }}">
            @csrf
            <flux:button type="submit" icon="arrow-path">Przelicz ponownie</flux:button>
        </form>

        <form method="POST" action="{{ route('tenant-settlements.finalize', $settlement) }}"
              onsubmit="return confirm(@js('Zatwierdzenie zablokuje dalsze zmiany tego rozliczenia. Kontynuować?'));">
            @csrf
            <flux:button type="submit" variant="primary" icon="lock-closed">Zatwierdź</flux:button>
        </form>
    @endunless
@endsection

@section('content')
    <flux:card>
        <dl class="grid gap-x-8 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <x-detail label="Numer">{{ $settlement->number }}</x-detail>
            <x-detail label="Nieruchomość">{{ $settlement->unit->property->name }}</x-detail>
            <x-detail label="Najemca">{{ $settlement->tenant?->name ?? '—' }}</x-detail>
            <x-detail label="Status">
                <flux:badge size="sm" :color="$settlement->isFinal() ? 'green' : 'yellow'">{{ $settlement->status->label() }}</flux:badge>
            </x-detail>
        </dl>
    </flux:card>

    @if ($settlement->isFinal())
        <flux:callout variant="success" icon="lock-closed" heading="Rozliczenie zatwierdzone">
            <flux:callout.text>Dane wejściowe są zamrożone — korekta wymaga usunięcia i utworzenia nowego rozliczenia.</flux:callout.text>
        </flux:callout>

        <flux:card>
            <flux:heading size="lg">Wyślij rozliczenie e-mailem</flux:heading>
            <flux:subheading>PDF trafi do wiadomości jako załącznik.</flux:subheading>

            <form method="POST" action="{{ route('tenant-settlements.email', $settlement) }}" class="mt-3 space-y-4">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input name="email" type="email" label="Adres e-mail" required
                        :value="old('email', auth()->user()->email)" />

                    <flux:input name="subject" label="Temat" required
                        :value="old('subject', \App\Mail\TenantSettlementMail::defaultSubject($settlement))" />
                </div>

                <flux:textarea name="body" label="Treść wiadomości" rows="6" required
                    description="Pod treścią automatycznie dopisywane są dane lokalu i kwoty, a PDF idzie w załączniku."
                >{{ old('body', \App\Mail\TenantSettlementMail::defaultBody($settlement)) }}</flux:textarea>

                <flux:button type="submit" variant="primary" icon="paper-airplane">Wyślij</flux:button>
            </form>
        </flux:card>
    @endif

    @include('tenant-settlements.lines-table', [
        'lines' => $settlement->lines,
        'periodStart' => $settlement->month->copy(),
        'periodEnd' => $settlement->month->copy()->addMonth(),
        'totalNet' => $settlement->total_net,
        'totalVat' => $settlement->vatAmount(),
        'totalGross' => $settlement->total_gross,
        'vatRate' => $settlement->vat_rate,
    ])
@endsection
