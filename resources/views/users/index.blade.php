@extends('layouts.app')

@section('title', 'Użytkownicy')
@section('heading', 'Użytkownicy')
@section('subheading', 'Dostęp do aplikacji wymaga zatwierdzenia konta przez administratora')

@section('content')
    <flux:card>
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Oczekujące na zatwierdzenie</flux:heading>
            @if ($pending->isNotEmpty())
                <flux:badge size="sm" color="amber">{{ $pending->count() }}</flux:badge>
            @endif
        </div>

        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>Imię i nazwisko</flux:table.column>
                <flux:table.column>E-mail</flux:table.column>
                <flux:table.column>Zarejestrowano</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($pending as $user)
                    <flux:table.row>
                        <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>{{ $user->created_at->format('d.m.Y H:i') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <form method="POST" action="{{ route('users.approve', $user) }}" class="inline">
                                @csrf
                                <flux:button type="submit" size="sm" variant="primary" icon="check">Zatwierdź</flux:button>
                            </form>
                            <x-delete-button :action="route('users.destroy', $user)" label="Odrzuć i usuń konto"
                                confirm="Usunąć konto {{ $user->email }}? Tej operacji nie można cofnąć." />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">Brak kont oczekujących na zatwierdzenie.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card>
        <flux:heading size="lg">Konta z dostępem</flux:heading>

        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>Imię i nazwisko</flux:table.column>
                <flux:table.column>E-mail</flux:table.column>
                <flux:table.column>Rola</flux:table.column>
                <flux:table.column>Zatwierdzono</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($users as $user)
                    <flux:table.row>
                        <flux:table.cell variant="strong">
                            {{ $user->name }}
                            @if ($user->is(auth()->user()))
                                <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">(Ty)</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$user->isAdmin() ? 'purple' : 'zinc'">
                                {{ $user->isAdmin() ? 'administrator' : 'pracownik' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->approved_at?->format('d.m.Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @unless ($user->is(auth()->user()))
                                <form method="POST" action="{{ route('users.admin', $user) }}" class="inline">
                                    @csrf
                                    <flux:button type="submit" size="sm" variant="ghost"
                                        :icon="$user->isAdmin() ? 'user-minus' : 'user-plus'"
                                        :tooltip="$user->isAdmin() ? 'Odbierz prawa administratora' : 'Nadaj prawa administratora'" />
                                </form>
                                <form method="POST" action="{{ route('users.revoke', $user) }}" class="inline"
                                      onsubmit="return confirm(@js('Cofnąć dostęp dla '.$user->email.'?'));">
                                    @csrf
                                    <flux:button type="submit" size="sm" variant="ghost" icon="lock-closed" tooltip="Cofnij dostęp" />
                                </form>
                            @endunless
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
@endsection
