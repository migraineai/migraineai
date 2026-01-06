<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEpisodeRequest;
use App\Http\Requests\UpdateEpisodeRequest;
use App\Models\AudioClip;
use App\Models\Episode;
use App\Services\EpisodeInsightsService;
use App\Services\ReminderScheduler;
use App\Services\TelemetryService;
use Illuminate\Support\Arr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EpisodeController extends Controller
{
    public function __construct(
        private readonly ReminderScheduler $reminderScheduler,
        private readonly TelemetryService $telemetry,
        private readonly EpisodeInsightsService $episodeInsights
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $range = (int)$request->integer('range', 30);

        $insights = $this->episodeInsights->build($user, $range);

        return response()->json($insights);
    }

    public function store(StoreEpisodeRequest $request): JsonResponse
    {
        $user = $request->user();

        $audioClip = null;

        if ($request->filled('audio_clip_id')) {
            /** @var AudioClip $audioClip */
            $audioClip = $user->audioClips()->findOrFail($request->input('audio_clip_id'));
        }

        $payload = $this->buildEpisodePayload($request, $audioClip) + ['user_id' => $user->id];

        if ($audioClip) {
            $episode = Episode::updateOrCreate(
                ['audio_clip_id' => $audioClip->id],
                $payload
            );
        } else {
            $episode = Episode::create($payload);
        }

        $episode->refresh();

        if ($audioClip) {
            $audioClip->touch();
        }

        $this->telemetry->record($user, 'log_save', [
            'episode_id' => $episode->id,
            'audio_clip_id' => $episode->audio_clip_id,
        ]);

        $this->reminderScheduler->schedulePostAttackFollowUp($user, $episode);

        return response()->json([
            'episode' => $episode,
        ], 201);
    }

    public function update(UpdateEpisodeRequest $request, Episode $episode): JsonResponse
    {
        $user = $request->user();

        abort_unless($episode->user_id === $user->id, 404);

        // Only update fields that are actually present in the request
        $payload = [];
        
        // Map of request keys to model attributes
        $fieldMap = [
            'start_time' => 'start_time',
            'end_time' => 'end_time',
            'intensity' => 'intensity',
            'pain_location' => 'pain_location',
            'aura' => 'aura',
            'symptoms' => 'symptoms',
            'triggers' => 'triggers',
            'what_you_tried' => 'what_you_tried',
            'notes' => 'notes',
            'transcript_text' => 'transcript_text',
            'extraction_confidences' => 'extraction_confidences',
        ];
        
        foreach ($fieldMap as $requestKey => $modelAttribute) {
            if ($request->has($requestKey)) {
                // Use the normalized/validated value from the request
                $payload[$modelAttribute] = $request->input($requestKey);
            }
        }

        $episode->fill($payload);

        $episode->save();

        return response()->json([
            'episode' => $episode->fresh(),
        ]);
    }

    public function destroy(Request $request, Episode $episode): JsonResponse
    {
        $user = $request->user();

        abort_unless($episode->user_id === $user->id, 404);

        $episode->delete();

        return response()->json([
            'status' => 'deleted',
        ]);
    }

    private function buildEpisodePayload(StoreEpisodeRequest $request, ?AudioClip $audioClip): array
    {
        $structured = $audioClip?->structured_payload ?? [];

        $startTime = $request->input('start_time') ?? Arr::get($structured, 'start_time');
        $endTime = $request->input('end_time') ?? Arr::get($structured, 'end_time');
        $intensity = $this->normalizeIntensity($request->input('intensity') ?? Arr::get($structured, 'intensity'));
        $painLocation = $this->normalizePainLocation($request->input('pain_location') ?? Arr::get($structured, 'pain_location'));
        $aura = $request->has('aura') ? (bool)$request->boolean('aura') : Arr::get($structured, 'aura');
        $triggers = $this->normalizeStringArray($request->input('triggers') ?? Arr::get($structured, 'triggers'));
        $symptoms = $this->normalizeStringArray($request->input('symptoms') ?? Arr::get($structured, 'symptoms'));
        $whatYouTried = $request->input('what_you_tried') ?? Arr::get($structured, 'what_you_tried');

        $notes = $request->input('notes') ?? Arr::get($structured, 'notes') ?? $audioClip?->transcript_text;
        $transcript = $request->input('transcript_text') ?? $audioClip?->transcript_text;

        return [
            'audio_clip_id' => $audioClip?->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'intensity' => $intensity,
            'pain_location' => $painLocation,
            'aura' => $aura,
            'symptoms' => $symptoms,
            'triggers' => $triggers,
            'what_you_tried' => $whatYouTried,
            'notes' => $notes,
            'transcript_text' => $transcript,
            'extraction_confidences' => $request->input('extraction_confidences') ?? Arr::get($structured, 'confidence_breakdown'),
        ];
    }

    private function mergeExistingEpisodeData(
        StoreEpisodeRequest $request,
        Episode $episode,
        array $payload
    ): array {
        $fields = [
            'audio_clip_id',
            'start_time',
            'end_time',
            'intensity',
            'pain_location',
            'aura',
            'symptoms',
            'triggers',
            'what_you_tried',
            'notes',
            'transcript_text',
            'extraction_confidences',
        ];

        foreach ($fields as $field) {
            if (!$request->exists($field)) {
                $payload[$field] = $episode->{$field};
            }
        }

        return $payload;
    }

    private function normalizeIntensity(mixed $value): ?int
    {
        if (is_numeric($value)) {
            $intensity = (int)round((float)$value);
            return ($intensity >= 0 && $intensity <= 10) ? $intensity : null;
        }

        return null;
    }

    private function normalizePainLocation(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        // Return the trimmed value as-is, accepting any string
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeStringArray(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $normalized = array_values(
            array_filter(
                array_map(
                    static fn ($item) => is_string($item) ? strtolower(trim($item)) : null,
                    $value
                ),
                static fn ($item) => $item !== null && $item !== ''
            )
        );

        return $normalized === [] ? null : $normalized;
    }
}
