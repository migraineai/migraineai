<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class DataExportService
{
    /**
     * Build a user export archive (ZIP) containing profile, episode, and audio clip data.
     *
     * @return array{disk: string, path: string, size_bytes: int|null, filename: string}
     */
    public function generate(User $user): array
    {
        $diskName = config('filesystems.exports_disk', 'local');
        $disk = Storage::disk($diskName);

        $directory = sprintf('exports/%d', $user->id);
        $disk->makeDirectory($directory);

        $filename = now()->utc()->format('Ymd_His') . '_migraineai_export.zip';
        $relativePath = $directory . '/' . $filename;
        $absolutePath = $disk->path($relativePath);

        $zip = new ZipArchive();

        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create export archive.');
        }

        $profile = $this->buildProfilePayload($user);
        $episodes = $this->buildEpisodesPayload($user);
        $audioClips = $this->buildAudioClipsPayload($user);

        $metadata = [
            'generated_at' => now()->utc()->toIso8601String(),
            'episode_count' => count($episodes),
            'audio_clip_count' => count($audioClips),
            'profile_fields' => array_keys($profile),
        ];

        $zip->addFromString('profile.json', $this->encodeJson($profile));
        $zip->addFromString('episodes.json', $this->encodeJson($episodes));
        $zip->addFromString('audio_clips.json', $this->encodeJson($audioClips));
        $zip->addFromString('metadata.json', $this->encodeJson($metadata));
        $zip->addFromString('README.txt', $this->buildReadme());

        $zip->close();

        $size = @filesize($absolutePath);

        return [
            'disk' => $diskName,
            'path' => $relativePath,
            'size_bytes' => $size === false ? null : $size,
            'filename' => $filename,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProfilePayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'age_range' => $user->age_range,
            'gender' => $user->gender,
            'time_zone' => $user->time_zone,
            'cycle_tracking_enabled' => (bool)$user->cycle_tracking_enabled,
            'cycle_length_days' => $user->cycle_length_days,
            'period_length_days' => $user->period_length_days,
            'last_period_start_date' => optional($user->last_period_start_date)?->toDateString(),
            'created_at' => optional($user->created_at)?->toIso8601String(),
            'updated_at' => optional($user->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildEpisodesPayload(User $user): array
    {
        return $user->episodes()
            ->with('audioClip')
            ->orderByDesc('start_time')
            ->get()
            ->map(function ($episode) {
                /** @var \App\Models\Episode $episode */
                return [
                    'id' => $episode->id,
                    'start_time' => optional($episode->start_time)?->toIso8601String(),
                    'end_time' => optional($episode->end_time)?->toIso8601String(),
                    'intensity' => $episode->intensity,
                    'pain_location' => $episode->pain_location,
                    'aura' => $episode->aura,
                    'symptoms' => $episode->symptoms,
                    'triggers' => $episode->triggers,
                    'what_you_tried' => $episode->what_you_tried,
                    'notes' => $episode->notes,
                    'created_at' => optional($episode->created_at)?->toIso8601String(),
                    'updated_at' => optional($episode->updated_at)?->toIso8601String(),
                    'audio_clip' => $episode->audioClip ? [
                        'id' => $episode->audioClip->id,
                        'status' => $episode->audioClip->status,
                        'storage_path' => $episode->audioClip->storage_path,
                        'transcript_text' => $episode->audioClip->transcript_text,
                        'structured_payload' => $episode->audioClip->structured_payload,
                        'processed_at' => optional($episode->audioClip->processed_at)?->toIso8601String(),
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAudioClipsPayload(User $user): array
    {
        return $user->audioClips()
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($clip) {
                /** @var \App\Models\AudioClip $clip */
                return [
                    'id' => $clip->id,
                    'status' => $clip->status,
                    'storage_path' => $clip->storage_path,
                    'duration_sec' => $clip->duration_sec,
                    'codec' => $clip->codec,
                    'sample_rate' => $clip->sample_rate,
                    'transcript_text' => $clip->transcript_text,
                    'structured_payload' => $clip->structured_payload,
                    'asr_provider' => $clip->asr_provider,
                    'asr_confidence' => $clip->asr_confidence,
                    'created_at' => optional($clip->created_at)?->toIso8601String(),
                    'updated_at' => optional($clip->updated_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function encodeJson(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    private function buildReadme(): string
    {
        return <<<TXT
MigraineAI Export
=================

Files in this archive:
- profile.json: Account-level profile preferences.
- episodes.json: Logged migraine episodes with structured fields.
- audio_clips.json: Voice log metadata, transcripts, and AI analysis payloads.
- metadata.json: Summary metrics and export timestamps.

Need help? Contact support at support@migraine.ai.
TXT;
    }
}
