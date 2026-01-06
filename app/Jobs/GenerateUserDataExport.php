<?php

namespace App\Jobs;

use App\Models\UserDataExport;
use App\Services\DataExportService;
use App\Services\TelemetryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GenerateUserDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(private readonly int $exportId)
    {
    }

    public function handle(DataExportService $service, TelemetryService $telemetry): void
    {
        $export = UserDataExport::query()
            ->with('user')
            ->find($this->exportId);

        if (!$export || !$export->user) {
            return;
        }

        if (!in_array($export->status, [UserDataExport::STATUS_PENDING, UserDataExport::STATUS_IN_PROGRESS], true)) {
            return;
        }

        $export->update([
            'status' => UserDataExport::STATUS_IN_PROGRESS,
            'error_message' => null,
        ]);

        try {
            $archive = $service->generate($export->user);

            $export->update([
                'status' => UserDataExport::STATUS_READY,
                'disk' => $archive['disk'],
                'path' => $archive['path'],
                'size_bytes' => $archive['size_bytes'],
                'download_token' => Str::random(48),
                'expires_at' => now()->addDays(7),
            ]);

            $telemetry->record($export->user, 'pdf_export', [
                'export_id' => $export->id,
                'size_bytes' => $archive['size_bytes'],
            ]);
        } catch (Throwable $exception) {
            $export->markFailed($exception->getMessage());
            Log::error('User data export failed', [
                'export_id' => $export->id,
                'user_id' => $export->user_id,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }
}
