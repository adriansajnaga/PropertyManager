<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        // Pierwsze konto w pustym systemie musi być administratorem i mieć dostęp od razu,
        // bo inaczej nie miałby kto zatwierdzać kolejnych rejestracji.
        $isFirstUser = User::count() === 0;

        $validated['is_admin'] = $isFirstUser;
        $validated['approved_at'] = $isFirstUser ? now() : null;

        event(new Registered(($user = User::create($validated))));

        if ($isFirstUser) {
            Auth::login($user);

            $this->redirect(route('dashboard'), navigate: true);

            return;
        }

        session()->flash('status', 'Konto zostało założone i czeka na zatwierdzenie przez administratora. Otrzymasz dostęp po akceptacji.');

        $this->redirect(route('login'), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Załóż konto" description="Dostęp otrzymasz po zatwierdzeniu konta przez administratora" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <!-- Name -->
        <div class="grid gap-2">
            <flux:input wire:model="name" id="name" label="{{ __('Imię i nazwisko') }}" type="text" name="name" required autofocus autocomplete="name" placeholder="Jan Kowalski" />
        </div>

        <!-- Email Address -->
        <div class="grid gap-2">
            <flux:input wire:model="email" id="email" label="{{ __('Adres e-mail') }}" type="email" name="email" required autocomplete="email" placeholder="email@example.com" />
        </div>

        <!-- Password -->
        <div class="grid gap-2">
            <flux:input
                wire:model="password"
                id="password"
                label="{{ __('Hasło') }}"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="Hasło"
            />
        </div>

        <!-- Confirm Password -->
        <div class="grid gap-2">
            <flux:input
                wire:model="password_confirmation"
                id="password_confirmation"
                label="{{ __('Powtórz hasło') }}"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Powtórz hasło"
            />
        </div>

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Załóż konto') }}
            </flux:button>
        </div>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        Masz już konto?
        <x-text-link href="{{ route('login') }}">Zaloguj się</x-text-link>
    </div>
</div>
