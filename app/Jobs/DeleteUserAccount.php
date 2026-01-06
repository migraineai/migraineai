<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserDeletionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeleteUserAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(private readonly int $deletionRequestId)
    {
    }

    public function handle(): void
    {
        $request = UserDeletionRequest::query()
            ->with(['user.audioClips'])
            ->find($this->deletionRequestId);

        if (!$request || !$request->user) {
            return;
        }

        if (!in_array($request->status, [UserDeletionRequest::STATUS_PENDING, UserDeletionRequest::STATUS_SCHEDULED], true)) {
            return;
        }

        $user = $request->user;

        $request->update([
            'status' => UserDeletionRequest::STATUS_PROCESSING,
            'error_message' => null,
        ]);

        try {
            $this->purgeAudio($user);

            $request->update([
                'status' => UserDeletionRequest::STATUS_COMPLETED,
                'processed_at' => now(),
            ]);

            $user->delete();
        } catch (Throwable $exception) {
            $request->markFailed($exception->getMessage());
            Log::error('User deletion failed', [
                'request_id' => $request->id,
                'user_id' => $request->user_id,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    private function purgeAudio(User $user): void
    {
        $disk = Storage::disk(config('filesystems.default', 'local'));

        foreach ($user->audioClips as $clip) {
            if ($clip->storage_path) {
                $disk->delete($clip->storage_path);
            }
        }
    }
}
