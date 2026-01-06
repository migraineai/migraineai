<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Display the login form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/AuthPortal', [
            'initialMode' => 'login',
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => __('The provided credentials do not match our records.'),
                ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        
        if ($user && !$user->hasVerifiedEmail()) {
            Auth::logout();
            
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            return redirect()->route('login')
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => __('Please verify your email address before logging in. Check your email for a verification link.'),
                ]);
        }

        // Clear any stored intended URL before redirecting super_admin
        if ($user?->role === 'super_admin') {
            $request->session()->forget('url.intended');
            return redirect()->route('super-admin.dashboard');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->forget(['impersonator_id', 'impersonator_name']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
