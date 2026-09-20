<?php

namespace App\Http\Controllers;

use App\Mail\TestMessageMail;
use App\Models\MailSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailSettingController extends Controller
{
    public function edit()
    {
        return view('mail-settings.edit', [
            'settings' => MailSetting::current(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'in:tls,ssl'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ], [], [
            'host' => 'serwer SMTP',
            'port' => 'port',
            'from_address' => 'adres nadawcy',
        ]);

        $settings = MailSetting::current();

        // Puste pole hasła zostawia dotychczasowe — żeby nie trzeba było go przepisywać.
        if (blank($data['password'])) {
            unset($data['password']);
        }

        $settings->fill($data)->save();

        return redirect()->route('mail-settings.edit')
            ->with('status', 'Ustawienia poczty zostały zapisane.');
    }

    public function test(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $settings = MailSetting::current();

        if (! $settings->isConfigured()) {
            return back()->withErrors(['email' => 'Najpierw zapisz ustawienia serwera poczty.']);
        }

        $settings->apply();

        try {
            Mail::to($data['email'])->send(new TestMessageMail);
        } catch (Throwable $e) {
            return back()->withErrors(['email' => 'Nie udało się wysłać: '.$e->getMessage()]);
        }

        return back()->with('status', "Wiadomość testowa została wysłana na adres {$data['email']}.");
    }
}
