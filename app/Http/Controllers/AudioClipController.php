<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAudioClipRequest;
use App\Jobs\ProcessAudioClip;
use App\Models\AudioClip;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AudioClipController extends Controller
{
    public function __construct(private readonly TelemetryService $telemetry)
    {
    }

    public function store(StoreAudioClipRequest $request): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('audio');

        $storagePath = $file->storeAs(
            sprintf('audio-clips/%d', $user->id),
            sprintf('%s.%s', Str::uuid()->toString(), $file->getClientOriginalExtension() ?: 'webm')
        );

        $audioClip = $user->audioClips()->create([
            'storage_path' => $storagePath,
            'duration_sec' => $request->integer('duration_sec'),
            'codec' => $request->input('codec'),
            'sample_rate' => $request->input('sample_rate'),
        ]);

        ProcessAudioClip::dispatch($audioClip->id);

        $this->telemetry->record($user, 'log_start', [
            'audio_clip_id' => $audioClip->id,
            'duration_sec' => $audioClip->duration_sec,
        ]);

        return response()->json([
            'id' => $audioClip->id,
            'status' => 'queued',
        ], 201);
    }

    public function show(AudioClip $audioClip): JsonResponse
    {
        $this->authorizeClip($audioClip);

        return response()->json([
            'id' => $audioClip->id,
            'status' => $audioClip->status,
            'duration_sec' => $audioClip->duration_sec,
            'codec' => $audioClip->codec,
            'sample_rate' => $audioClip->sample_rate,
            'transcript_text' => $audioClip->transcript_text,
            'asr_confidence' => $audioClip->asr_confidence,
            'structured_payload' => $audioClip->structured_payload,
            'analysis_error' => $audioClip->analysis_error,
            'processed_at' => optional($audioClip->processed_at)?->toIso8601String(),
            'updated_at' => $audioClip->updated_at->toIso8601String(),
        ]);
    }

    private function authorizeClip(AudioClip $audioClip): void
    {
        abort_unless(auth()->id() === $audioClip->user_id, 404);
    }
}
