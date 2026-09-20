<x-layouts.auth.simple>
    <div class="flex flex-col gap-5">
        <p class="text-sm leading-relaxed" style="color: #d2d3d3;">
            Aplikacja do zarządzania nieruchomościami: ewidencja lokali, najemców i liczników,
            odczyty mediów oraz miesięczne rozliczenia kosztów wody, prądu, ciepła i eksploatacji budynku.
        </p>

        <p class="text-xs" style="color: #8f9192;">
            Dostęp wyłącznie dla pracowników biura. Nowe konta wymagają zatwierdzenia przez administratora.
        </p>

        @auth
            <flux:button variant="primary" class="w-full" :href="route('overview')" wire:navigate>Przejdź do aplikacji</flux:button>
        @else
            <div class="flex flex-col gap-3">
                <flux:button variant="primary" class="w-full" :href="route('login')" wire:navigate>Zaloguj się</flux:button>
                <flux:button variant="ghost" class="w-full" :href="route('register')" wire:navigate>Załóż konto</flux:button>
            </div>
        @endauth
    </div>
</x-layouts.auth.simple>
