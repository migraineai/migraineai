<?php

use App\Http\Middleware\EnsureImpersonating;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Jobs\ScheduleDailyReminders;
use App\Services\TelemetryService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\FixMobileSession::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/email/resend-verification',
        ]);

        $middleware->alias([
            'super_admin' => EnsureSuperAdmin::class,
            'impersonating' => EnsureImpersonating::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->job(new ScheduleDailyReminders())->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $throwable) {
            if (App::runningUnitTests()) {
                return;
            }

            try {
                /** @var TelemetryService $telemetry */
                $telemetry = app(TelemetryService::class);
                $telemetry->record(Auth::user(), 'crash', [
                    'exception' => $throwable::class,
                    'message' => $throwable->getMessage(),
                ]);
            } catch (Throwable) {
                // Swallow telemetry failures to avoid recursive reporting
            }
        });
    })->create();
