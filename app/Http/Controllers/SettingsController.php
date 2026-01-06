<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Jobs\DeleteUserAccount;
use App\Jobs\GenerateUserDataExport;
use App\Models\UserDataExport;
use App\Models\UserDeletionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'age_range' => $user->age_range,
                'gender' => $user->gender,
                'time_zone' => $user->time_zone,
                'onboarding_answers' => $user->onboarding_answers ?? [],
                'cycle_tracking_enabled' => (bool)$user->cycle_tracking_enabled,
                'cycle_length_days' => $user->cycle_length_days,
                'period_length_days' => $user->period_length_days,
                'last_period_start_date' => optional($user->last_period_start_date)?->toDateString(),
                'daily_reminder_enabled' => (bool)$user->daily_reminder_enabled,
                'daily_reminder_hour' => $user->daily_reminder_hour,
                'post_attack_follow_up_hours' => $user->post_attack_follow_up_hours,
            ],
            'options' => [
                'age_ranges' => ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'],
                'genders' => [
                    ['label' => 'Female', 'value' => 'female'],
                    ['label' => 'Male', 'value' => 'male'],
                    ['label' => 'Non-binary', 'value' => 'non_binary'],
                    ['label' => 'Prefer not to say', 'value' => 'prefer_not_to_say'],
                ],
                'time_zones' => [
                    'America/New_York',
                    'America/Los_Angeles',
                    'Europe/London',
                    'Europe/Berlin',
                    'Asia/Kolkata',
                    'Asia/Tokyo',
                    'Australia/Sydney',
                ],
                'reminder_hours' => range(0, 23),
            ],
            'exports' => $user->dataExports()
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn (UserDataExport $export) => $this->transformExport($export))
                ->values(),
            'deletion_request' => optional(
                $user->deletionRequests()->orderByDesc('created_at')->first(),
                fn (UserDeletionRequest $request) => $this->transformDeletionRequest($request)
            ),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->validated());

        return response()->json([
            'message' => 'Profile updated successfully.',
            'profile' => $user->fresh(),
        ]);
    }

    public function requestExport(Request $request): JsonResponse
    {
        $user = $request->user();

        $existing = $user->dataExports()
            ->whereIn('status', [UserDataExport::STATUS_PENDING, UserDataExport::STATUS_IN_PROGRESS])
            ->latest()
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'We are already preparing your latest export.',
                'export' => $this->transformExport($existing),
            ], 202);
        }

        $export = $user->dataExports()->create([
            'status' => UserDataExport::STATUS_PENDING,
            'disk' => config('filesystems.exports_disk', 'local'),
        ]);

        Log::info('User requested data export', ['user_id' => $user->id, 'export_id' => $export->id]);

        GenerateUserDataExport::dispatch($export->id);

        return response()->json([
            'message' => 'We are preparing your export now. Check back for a download link shortly.',
            'export' => $this->transformExport($export),
        ], 202);
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $user = $request->user();

        $active = $user->deletionRequests()
            ->whereIn(
                'status',
                [
                    UserDeletionRequest::STATUS_PENDING,
                    UserDeletionRequest::STATUS_SCHEDULED,
                    UserDeletionRequest::STATUS_PROCESSING,
                ]
            )
            ->latest()
            ->first();

        if ($active) {
            return response()->json([
                'message' => 'Your deletion request is already queued. We will notify you once it is complete.',
                'deletion_request' => $this->transformDeletionRequest($active),
            ], 202);
        }

        $scheduledFor = Carbon::now()->addDays(30);

        $deletionRequest = $user->deletionRequests()->create([
            'status' => UserDeletionRequest::STATUS_SCHEDULED,
            'scheduled_for' => $scheduledFor,
        ]);

        Log::info('User requested account deletion', [
            'user_id' => $user->id,
            'deletion_request_id' => $deletionRequest->id,
            'scheduled_for' => $scheduledFor->toIso8601String(),
        ]);

        if (config('queue.default') === 'sync') {
            Log::warning('Deletion request scheduled but queue driver is sync; run a queue worker to process deletions.', [
                'user_id' => $user->id,
                'deletion_request_id' => $deletionRequest->id,
            ]);
        } else {
            DeleteUserAccount::dispatch($deletionRequest->id)->delay($scheduledFor);
        }

        return response()->json([
            'message' => 'We will delete your account in 30 days. If this was a mistake, contact support immediately.',
            'deletion_request' => $this->transformDeletionRequest($deletionRequest),
        ], 202);
    }

    public function listExports(Request $request): JsonResponse
    {
        $exports = $request->user()
            ->dataExports()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (UserDataExport $export) => $this->transformExport($export))
            ->values();

        return response()->json([
            'exports' => $exports,
        ]);
    }

    public function downloadExport(Request $request, UserDataExport $export, string $token)
    {
        abort_unless($export->user_id === $request->user()->id, 404);

        if ($export->download_token !== $token) {
            abort(403);
        }

        if ($export->status !== UserDataExport::STATUS_READY || !$export->path) {
            abort(409);
        }

        if ($export->expires_at && $export->expires_at->isPast()) {
            abort(410);
        }

        $disk = Storage::disk($export->disk ?? config('filesystems.exports_disk', 'local'));

        abort_unless($disk->exists($export->path), 404);

        $filename = sprintf(
            'MigraineAI-export-%s.zip',
            optional($export->created_at)?->format('Ymd_His') ?? $export->id
        );

        return $disk->download($export->path, $filename);
    }

    private function transformExport(UserDataExport $export): array
    {
        return [
            'id' => $export->id,
            'status' => $export->status,
            'size_bytes' => $export->size_bytes,
            'created_at' => optional($export->created_at)?->toIso8601String(),
            'expires_at' => optional($export->expires_at)?->toIso8601String(),
            'error_message' => $export->error_message,
            'download_url' => $export->status === UserDataExport::STATUS_READY && $export->download_token && $export->path ?
                route('settings.export.download', ['export' => $export->id, 'token' => $export->download_token]) :
                null,
        ];
    }

    private function transformDeletionRequest(UserDeletionRequest $deletionRequest): array
    {
        return [
            'id' => $deletionRequest->id,
            'status' => $deletionRequest->status,
            'scheduled_for' => optional($deletionRequest->scheduled_for)?->toIso8601String(),
            'processed_at' => optional($deletionRequest->processed_at)?->toIso8601String(),
            'error_message' => $deletionRequest->error_message,
            'created_at' => optional($deletionRequest->created_at)?->toIso8601String(),
        ];
    }
}
