<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        if ($request->session()->has('impersonator_id')) {
            return back()->withErrors(['impersonate' => 'You are already impersonating another user.']);
        }

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['impersonate' => 'You are already logged in as this user.']);
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        $request->session()->put('impersonator_name', $request->user()->name);

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', "You are now impersonating {$user->name}.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        $request->session()->forget('impersonator_name');

        if (!$impersonatorId) {
            return redirect()->route('dashboard');
        }

        $admin = User::find($impersonatorId);

        if ($admin) {
            Auth::login($admin);
        } else {
            Auth::logout();
        }

        return redirect()->route('super-admin.dashboard')->with('success', 'Returned to super admin mode.');
    }
}
