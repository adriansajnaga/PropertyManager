<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo class="size-8" href="#"></x-app-logo>
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="PM Property Manager" class="grid">
                    <flux:navlist.item icon="home" :href="route('overview')" :current="request()->routeIs('overview')">Pulpit</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Ewidencja" class="grid">
                    <flux:navlist.item icon="building-office-2" :href="route('properties.index')" :current="request()->routeIs('properties.*')">Nieruchomości</flux:navlist.item>
                    <flux:navlist.item icon="home-modern" :href="route('units.index')" :current="request()->routeIs('units.*')">Lokale</flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('tenants.index')" :current="request()->routeIs('tenants.*')">Najemcy</flux:navlist.item>
                    <flux:navlist.item icon="cpu-chip" :href="route('meters.index')" :current="request()->routeIs('meters.*')">Liczniki</flux:navlist.item>
                    <flux:navlist.item icon="clipboard-document-list" :href="route('readings.index')" :current="request()->routeIs('readings.*')">Odczyty</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Koszty i rozliczenia" class="grid">
                    <flux:navlist.item icon="wrench-screwdriver" :href="route('maintenance-costs.index')" :current="request()->routeIs('maintenance-costs.*')">Koszty utrzymania</flux:navlist.item>
                    <flux:navlist.item icon="receipt-percent" :href="route('utility-bills.index')" :current="request()->routeIs('utility-bills.*')">Rachunki</flux:navlist.item>
                    <flux:navlist.item icon="fire" :href="route('heat-settlements.index')" :current="request()->routeIs('heat-settlements.*')">Koszty ciepła</flux:navlist.item>
                    <flux:navlist.item icon="banknotes" :href="route('rent-charges.index')" :current="request()->routeIs('rent-charges.*')">Czynsz</flux:navlist.item>
                    <flux:navlist.item icon="calculator" :href="route('tenant-settlements.index')" :current="request()->routeIs('tenant-settlements.*')">Rozliczenia</flux:navlist.item>
                </flux:navlist.group>
                @if (auth()->user()?->isAdmin())
                    @php($pendingUsers = \App\Models\User::whereNull('approved_at')->count())
                    <flux:navlist.group heading="Administracja" class="grid">
                        <flux:navlist.item icon="user-group" :href="route('users.index')" :current="request()->routeIs('users.*')"
                            :badge="$pendingUsers ?: null" badge-color="amber">
                            Użytkownicy
                        </flux:navlist.item>
                        <flux:navlist.item icon="envelope" :href="route('mail-settings.edit')" :current="request()->routeIs('mail-settings.*')">
                            Ustawienia poczty
                        </flux:navlist.item>
                        <flux:navlist.item icon="document-check" :href="route('ksef-settings.edit')" :current="request()->routeIs('ksef-settings.*')">
                            KSeF
                        </flux:navlist.item>
                    </flux:navlist.group>
                @endif
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>Ustawienia konta</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group x-data x-model="$flux.appearance">
                        <flux:menu.radio value="light" icon="sun">Motyw jasny</flux:menu.radio>
                        <flux:menu.radio value="dark" icon="moon">Motyw ciemny</flux:menu.radio>
                        <flux:menu.radio value="system" icon="computer-desktop">Jak w systemie</flux:menu.radio>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Wyloguj się
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>Ustawienia konta</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group x-data x-model="$flux.appearance">
                        <flux:menu.radio value="light" icon="sun">Motyw jasny</flux:menu.radio>
                        <flux:menu.radio value="dark" icon="moon">Motyw ciemny</flux:menu.radio>
                        <flux:menu.radio value="system" icon="computer-desktop">Jak w systemie</flux:menu.radio>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Wyloguj się
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
