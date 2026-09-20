<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsApproved
{
    /**
     * Samo wypełnienie formularza rejestracji nie daje dostępu — konto musi
     * zostać zatwierdzone przez administratora.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null && ! $user->isApproved()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Konto czeka na zatwierdzenie przez administratora.',
            ]);
        }

        return $next($request);
    }
}
