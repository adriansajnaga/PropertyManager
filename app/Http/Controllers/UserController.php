<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', [
            'pending' => User::whereNull('approved_at')->orderBy('created_at')->get(),
            'users' => User::whereNotNull('approved_at')->orderBy('name')->get(),
        ]);
    }

    public function approve(User $user)
    {
        $user->approve();

        return back()->with('status', "Konto {$user->email} zostało zatwierdzone.");
    }

    public function revoke(Request $request, User $user)
    {
        if ($this->isSelf($request, $user)) {
            return back()->withErrors(['user' => 'Nie możesz cofnąć dostępu samemu sobie.']);
        }

        $user->revokeApproval();

        return back()->with('status', "Dostęp dla {$user->email} został cofnięty.");
    }

    public function toggleAdmin(Request $request, User $user)
    {
        if ($this->isSelf($request, $user)) {
            return back()->withErrors(['user' => 'Nie możesz odebrać uprawnień administratora samemu sobie.']);
        }

        $user->update(['is_admin' => ! $user->isAdmin()]);

        return back()->with('status', $user->isAdmin()
            ? "{$user->email} jest teraz administratorem."
            : "{$user->email} nie jest już administratorem.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($this->isSelf($request, $user)) {
            return back()->withErrors(['user' => 'Nie możesz usunąć własnego konta.']);
        }

        $email = $user->email;
        $user->delete();

        return back()->with('status', "Konto {$email} zostało usunięte.");
    }

    private function isSelf(Request $request, User $user): bool
    {
        return $request->user()->is($user);
    }
}
