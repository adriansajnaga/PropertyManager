<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::user()?->isAdmin(), 403, 'Ta sekcja jest dostępna tylko dla administratora.');

        return $next($request);
    }
}
