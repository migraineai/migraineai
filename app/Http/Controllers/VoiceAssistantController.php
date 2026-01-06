<?php

namespace App\Http\Controllers;

use App\Services\OpenAIService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class VoiceAssistantController extends Controller
{
    public function __construct(private readonly OpenAIService $openAI)
    {
    }

    public function createRealtimeSession(Request $request): JsonResponse
    {
        $promptOptions = $this->promptSessionOptions();

        if ($promptOptions === null) {
            return $this->attemptFallbackSession();
        }

        try {
            $session = $this->openAI->createRealtimeSessionToken($promptOptions);

            return response()->json($session);
        } catch (RequestException $promptFailure) {
            \Log::warning('Realtime session prompt failed, retrying without managed prompt', [
                'message' => $promptFailure->getMessage(),
                'status' => optional($promptFailure->response)->status(),
                'body' => optional($promptFailure->response)->body(),
            ]);

            return $this->attemptFallbackSession($promptFailure);
        } catch (Throwable $exception) {
            \Log::error('createRealtimeSession error', [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return response()->json([
                'message' => 'Unable to start realtime session.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    private function attemptFallbackSession(?Throwable $previous = null): JsonResponse
    {
        try {
            $session = $this->openAI->createRealtimeSessionToken($this->fallbackSessionOptions());

            return response()->json($session);
        } catch (Throwable $exception) {
            \Log::error('createRealtimeSession fallback error', [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'previous_error' => $previous?->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to start realtime session.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    private function promptSessionOptions(): ?array
    {
        $promptId = config('services.openai.realtime_prompt_id', 'pmpt_692ffc99b1e88194865e3c762c85a7fa021e8e0fc9ce8936');

        if (!$promptId) {
            return null;
        }

        return [
            'prompt_id' => $promptId,
            'prompt_version' => (string)config('services.openai.realtime_prompt_version', '48'),
        ];
    }

    private function fallbackSessionOptions(): array
    {
        return [
            'voice' => 'alloy',
            'instructions' => $this->fallbackRealtimeInstructions(),
        ];
    }

    private function fallbackRealtimeInstructions(): string
    {
        return 'You are a compassionate MigraineAI assistant who helps users log migraine episodes. Reply in English, keep responses under two sentences, acknowledge their pain, and collect start time, triggers, intensity, pain location, and symptoms.';
    }
}
