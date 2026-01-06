<?php

namespace App\Jobs;

use App\Models\AudioClip;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Support\StructuredEpisodeMapper;
use Throwable;

class ProcessAudioClip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $audioClipId)
    {
    }

    public function handle(OpenAIService $openAI): void
    {
        $audioClip = AudioClip::query()->with('user')->find($this->audioClipId);

        if (!$audioClip) {
            return;
        }

        try {
            $audioClip->update([
                'status' => 'processing',
                'analysis_error' => null,
            ]);

            $transcription = $openAI->transcribeAudio($audioClip);
            $analysis = $openAI->extractEpisodeData($transcription->text);

            $audioClip->update([
                'transcript_text' => $transcription->text,
                'asr_confidence' => $transcription->confidence,
                'asr_provider' => $transcription->provider,
                'structured_payload' => StructuredEpisodeMapper::map($analysis),
                'processed_at' => now(),
                'status' => 'transcribed',
            ]);
        } catch (Throwable $exception) {
            //print the exception message to the log
            
            $audioClip->update([
                'status' => 'failed',
                'analysis_error' => $exception->getMessage(),
            ]);
            Log::error('Audio clip processing failed', [
                'audio_clip_id' => $audioClip->id,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }
}
