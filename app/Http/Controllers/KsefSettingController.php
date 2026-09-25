<?php

namespace App\Http\Controllers;

use App\Enums\KsefEnvironment;
use App\Models\KsefSetting;
use App\Services\Ksef\KsefClient;
use App\Services\Ksef\KsefException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Throwable;

class KsefSettingController extends Controller
{
    public function edit()
    {
        return view('ksef-settings.edit', [
            'settings' => KsefSetting::current(),
            'environments' => KsefEnvironment::cases(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'environment' => ['required', Rule::enum(KsefEnvironment::class)],
            'nip' => ['required', 'string', 'max:20'],
            'token' => ['nullable', 'string', 'max:255'],
        ], [], [
            'environment' => 'środowisko',
            'nip' => 'NIP',
            'token' => 'token KSeF',
        ]);

        $settings = KsefSetting::current();

        // Puste pole tokena zostawia dotychczasowy — tak samo jak przy haśle poczty.
        if (blank($data['token'])) {
            unset($data['token']);
        }

        $settings->fill($data)->save();

        // Zmiana środowiska albo tokena unieważnia token dostępowy z cache.
        Cache::forget('ksef.access-token.'.$settings->environment->value.'.'.$settings->nip);

        return redirect()->route('ksef-settings.edit')
            ->with('status', 'Ustawienia KSeF zostały zapisane.');
    }

    public function test()
    {
        $settings = KsefSetting::current();

        if (! $settings->isConfigured()) {
            return back()->withErrors(['token' => 'Najpierw zapisz NIP i token KSeF.']);
        }

        try {
            $session = KsefClient::wrapConnectionErrors(
                fn () => (new KsefClient($settings))->currentSession(),
            );
        } catch (KsefException $e) {
            return back()->withErrors(['token' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['token' => 'Nie udało się połączyć z KSeF: '.$e->getMessage()]);
        }

        $settings->forceFill(['verified_at' => now()])->save();

        return back()->with('status', sprintf(
            'Połączenie z KSeF (%s) działa. Kontekst: %s.',
            $settings->environment->label(),
            $session['contextIdentifier']['value'] ?? $settings->nip,
        ));
    }
}
