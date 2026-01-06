<?php

use App\Http\Controllers\AudioClipController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ClinicianReportController;
use App\Http\Controllers\EpisodeController;
use App\Http\Controllers\PeriodLogController;
use App\Http\Controllers\PeriodTrackingController;
use App\Http\Controllers\ProfileController as UserProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Http\Controllers\SuperAdmin\ProfileController as SuperAdminProfileController;
use App\Http\Controllers\SuperAdmin\UserController as SuperAdminUserController;
use App\Http\Controllers\VoiceAssistantController;
use App\Services\EpisodeInsightsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user && $user->role === 'super_admin') {
            return redirect()->route('super-admin.dashboard');
        }
        return redirect()->route('dashboard');
    }

    return Inertia::render('Landing');
})->name('landing');

Route::get('/privacy-policy', function () {
    return Inertia::render('PrivacyPolicy');
})->name('privacy');

Route::get('/terms-service', function () {
    return Inertia::render('TermsService');
})->name('terms');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'create'])
        ->name('password.request');
        
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])
        ->name('password.email');
        
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])
        ->name('password.reset');
        
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->name('password.update');    
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Unauthenticated email verification resend (for users at login)
Route::post('/email/resend-verification', [EmailVerificationController::class, 'resendFromLogin'])
    ->name('verification.resend-from-login')
    ->middleware(['throttle:6,1']);

// Email verification routes
// Public verification via signed link (no auth needed)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verifySigned'])
    ->name('verification.verify')
    ->middleware(['throttle:6,1']);

// Authenticated notice + resend
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->name('verification.send')
        ->middleware(['throttle:6,1']);
});

// Protected routes that require verification
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function (EpisodeInsightsService $episodeInsights) {
        $user = Auth::user();
        
        // Redirect super_admins to their dashboard
        if ($user && $user->role === 'super_admin') {
            return redirect()->route('super-admin.dashboard');
        }

        $insights = $user ? $episodeInsights->build($user, 30) : null;

        return Inertia::render('Dashboard', [
            'episodeInsights' => $insights,
        ]);
    })->name('dashboard');

    Route::get('/period-tracking', [PeriodTrackingController::class, 'index'])->name('period-tracking');
    Route::post('/period-tracking/logs', [PeriodLogController::class, 'store'])->name('period-tracking.logs.store');

    Route::get('/analytics', \App\Http\Controllers\AnalyticsController::class)->name('analytics');
    Route::get('/analytics/clinician-report/download', [ClinicianReportController::class, 'download'])
        ->name('analytics.clinician-report.download');
    Route::post('/analytics/clinician-report/send', [ClinicianReportController::class, 'send'])
        ->name('analytics.clinician-report.send');

    Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
    Route::put('/settings/profile', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/request-export', [SettingsController::class, 'requestExport'])->name('settings.export');

    // User tour status APIs
    Route::get('/user/tour-status', [\App\Http\Controllers\TourStatusController::class, 'show'])
        ->name('user.tour-status.show');
    Route::post('/user/tour-status', [\App\Http\Controllers\TourStatusController::class, 'update'])
        ->name('user.tour-status.update');
    Route::post('/settings/request-deletion', [SettingsController::class, 'requestDeletion'])->name('settings.delete');
    Route::get('/settings/exports', [SettingsController::class, 'listExports'])->name('settings.export.list');
    Route::get('/settings/exports/{export}/{token}', [SettingsController::class, 'downloadExport'])
        ->name('settings.export.download');

    Route::get('/profile', UserProfileController::class)->name('profile');
    
    // CSRF token refresh endpoint for mobile compatibility
    Route::get('/csrf-token', function () {
        return response()->json(['csrf_token' => csrf_token()]);
    });

    // Mobile-specific episodes endpoint with relaxed auth
    Route::get('/episodes-mobile', function (Request $request) {
        // Ensure user is authenticated via session
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        $user = Auth::user();
        $range = (int)$request->integer('range', 30);
        
        $episodeInsights = app(\App\Services\EpisodeInsightsService::class);
        $insights = $episodeInsights->build($user, $range);
        
        return response()->json($insights);
    })->middleware(['web']);

    Route::post('/audio-clips', [AudioClipController::class, 'store'])->name('audio-clips.store');
    Route::get('/audio-clips/{audioClip}', [AudioClipController::class, 'show'])->name('audio-clips.show');
    Route::get('/episodes', [EpisodeController::class, 'index'])->name('episodes.index');
    Route::post('/episodes', [EpisodeController::class, 'store'])->name('episodes.store');
    Route::put('/episodes/{episode}', [EpisodeController::class, 'update'])->name('episodes.update');
    Route::delete('/episodes/{episode}', [EpisodeController::class, 'destroy'])->name('episodes.destroy');
    Route::get('/voice/session', [VoiceAssistantController::class, 'createRealtimeSession'])->name('voice.session');

    Route::prefix('super-admin')
        ->as('super-admin.')
        ->middleware('super_admin')
        ->group(function () {
            Route::get('/dashboard', SuperAdminDashboardController::class)->name('dashboard');
            Route::put('/profile', [SuperAdminProfileController::class, 'updateProfile'])->name('profile.update');
            Route::put('/password', [SuperAdminProfileController::class, 'updatePassword'])->name('password.update');

            Route::put('/users/{user}', [SuperAdminUserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [SuperAdminUserController::class, 'destroy'])->name('users.destroy');
            Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'store'])->name('users.impersonate');
        });
});

Route::post('/super-admin/stop-impersonating', [ImpersonationController::class, 'destroy'])
    ->middleware(['auth', 'impersonating'])
    ->name('super-admin.stop-impersonating');
