<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();
        $isAdminLogin = $request->input('as') === 'admin';

        // If user is trying admin login but is not actually admin
        if ($isAdminLogin && ! $user->hasAdminAccess()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login', ['as' => 'admin'])->with('status', 'Akun ini bukan admin.');
        }

        // Admin dashboard
        if ($user->hasAdminAccess() && $isAdminLogin) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        // Admin users should also land on admin dashboard even without the admin flag
        if ($user->hasAdminAccess()) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        // Customer dashboard
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
