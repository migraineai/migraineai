<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    /**
     * Display the email verification notice.
     */
    public function notice(Request $request): Response
    {
        return Inertia::render('Auth/VerifyEmail', [
            'status' => session('status'),
        ]);
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard').'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard').'?verified=1');
    }

    /**
     * Verify email via signed link without requiring authentication.
     */
    public function verifySigned(Request $request, int $id, string $hash): RedirectResponse
    {
        Log::info('Email verifySigned invoked', ['id' => $id, 'hash' => $hash]);
        if (! URL::hasValidSignature($request)) {
            Log::warning('Email verification invalid signature');
            return redirect()->route('login')->with('error', 'Verification link is invalid or has expired.');
        }

        $user = User::find($id);

        if (! $user) {
            Log::warning('Email verification user not found', ['id' => $id]);
            return redirect()->route('login')->with('error', 'User not found for verification.');
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            Log::warning('Email verification hash mismatch', ['id' => $id]);
            return redirect()->route('login')->with('error', 'Verification link is invalid.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
            Log::info('Email verified successfully', ['id' => $id]);
        }

        return redirect()->route('login')->with('success', 'Your email has been verified. Please sign in.');
    }

    /**
     * Send a new email verification notification.
     */
    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    /**
     * Send verification email from login page (unauthenticated).
     */
    public function resendFromLogin(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified.',
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email sent successfully.',
        ], 200);
    }
}
