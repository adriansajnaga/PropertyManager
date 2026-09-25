<?php

namespace App\Providers;

use App\Models\MailSetting;
use App\Services\Ksef\KsefClient;
use App\Services\Sms\SmsapiGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bez tego kontener wstrzyknąłby pusty rekord ustawień zamiast zapisanych.
        $this->app->bind(KsefClient::class, fn () => KsefClient::forCurrentSettings());

        $this->app->bind(SmsGateway::class, fn () => new SmsapiGateway(
            config('services.smsapi.token'),
            config('services.smsapi.sender'),
            (bool) config('services.smsapi.test'),
        ));
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
