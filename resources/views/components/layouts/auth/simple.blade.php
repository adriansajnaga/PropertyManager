{{-- Stylistyka ekranów logowania wzorowana na ASCOMM TimeManager:
     ciemne tło, panel z polami, zielony przycisk akcji. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <link href="https://fonts.bunny.net/css?family=open-sans:400,600,700" rel="stylesheet" />
        <style>
            .pm-auth {
                --color-accent: #0f9e3e;
                --color-accent-content: #0f9e3e;
                --color-accent-foreground: #fff;
                font-family: 'Open Sans', sans-serif;
            }
            .pm-auth input:not([type='checkbox']) {
                background-color: #57595c;
                border-color: transparent;
                color: #fff;
                height: 45px;
                font-weight: 600;
            }
            .pm-auth input::placeholder { color: #b9babb; font-weight: 400; }
            .pm-auth button[type='submit'] { height: 45px; font-weight: 600; }
        </style>
    </head>
    <body class="min-h-screen antialiased" style="background-color: #16181a; color: #d2d3d3;">
        <div class="pm-auth flex min-h-svh flex-col items-center justify-center p-6">
            <div class="w-full max-w-md">
                <div class="mb-4 flex items-end justify-between px-1">
                    <a href="{{ route('home') }}" class="flex flex-col" wire:navigate>
                        <span style="font-size: 28px; line-height: 42px; font-weight: 300; color: #b1b2b3;">
                            <span style="font-weight: 700;">PROPERTY</span>MANAGER
                        </span>
                        <small style="font-size: 13px; line-height: 19.5px; color: #b1b2b3;">{{ config('pm.brand_owner') }}</small>
                    </a>
                    <flux:icon.lock-closed class="mb-2 size-8" style="color: #8f9192;" />
                </div>

                <div class="rounded-lg p-6" style="background-color: #2d3035;">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
