<?php

namespace App\Providers;

use App\Models\MailSetting;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ustawienia poczty z bazy nadpisują te z .env. Przed migracją tabeli
        // jeszcze nie ma, więc brak ustawień nie może wywrócić aplikacji.
        try {
            MailSetting::current()->apply();
        } catch (Throwable) {
            // zostają ustawienia z .env
        }
    }
}
