<?php

namespace App\Services;

use App\Models\AudioClip;
use App\Services\DTO\TranscriptionResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OpenAIService
{
    //private const WHISPER_MODEL = 'whisper-1';
    private const WHISPER_MODEL = 'gpt-4o-mini-transcribe-2025-12-15';
    private const CHAT_MODEL = 'gpt-4o-mini';
    //private const REALTIME_MODEL = 'gpt-4o-realtime-preview-2024-12-17';
    // Updated to newer realtime model version
    private const REALTIME_MODEL = 'gpt-4o-realtime-preview-2025-06-03';
    // Updated to use gpt-4o-mini-tts instead of tts-1
    private const TTS_MODEL = 'gpt-4o-mini-tts';
    private const FIELD_LABELS = [
        'start_time' => 'start time',
        'triggers' => 'triggers',
        'pain_location' => 'pain location',
        'intensity' => 'pain intensity',
        'symptoms' => 'symptoms',
    ];
private const VOICE_ASSISTANT_PROMPT = <<<'PROMPT'
You are a compassionate voice assistant helping users log their migraine episodes. Your role is to:

1. Acknowledge what the user has shared
2. Ask follow-up questions ONLY for missing information
3. Be empathetic - users are in pain
4. ALWAYS respond in English, even if the user speaks another language. Never answer in any other language.
5. If a field value is provisional/low-confidence, confirm it succinctly before moving on.

REQUIRED FIELDS TO COLLECT:
- start_time: When the migraine started (date and/or time)
- triggers: What might have caused it (stress, food, weather, sleep, hormones, etc.)
- intensity: Pain level on a scale of 1-10
- pain_location: Where the pain is located (left temple, right temple, forehead, back of head, whole head, etc.)
- symptoms: Other symptoms (nausea, vomiting, aura, light sensitivity, sound sensitivity, etc.)

IMPORTANT RULES:
1. ONLY ask about fields listed in "Still missing" - never re-ask about collected fields
2. Ask about ONE missing field at a time
3. Keep responses short (1-2 sentences max)
4. Be warm and empathetic
5. If all fields are collected, thank the user and confirm you're saving the episode
6. start_time: Ask ONCE. If user replies but no time detected, system will use current time automatically.
7. triggers: Ask ONCE. If user replies but no trigger detected, system will use "other" automatically.
8. symptoms: Ask ONCE. If user replies but no symptom detected, system will use "other" automatically.

You MUST respond with valid JSON in this exact format:
{
    "assistant_response": "Your spoken response to the user (the question or acknowledgment)",
    "is_followup_required": true,
    "next_question_field": "the_field_key_you_are_asking_about"
}

FIELD KEYS (use these exact values for next_question_field):
- "start_time" for when it started
- "triggers" for what triggered it
- "intensity" for pain level 1-10
- "pain_location" for where the pain is
- "symptoms" for other symptoms

EXAMPLE RESPONSES:

If missing start_time:
{
    "assistant_response": "When did this migraine start?",
    "is_followup_required": true,
    "next_question_field": "start_time"
}

If missing intensity:
{
    "assistant_response": "how intense is the pain?",
    "is_followup_required": true,
    "next_question_field": "intensity"
}

If all fields collected:
{
    "assistant_response": "Thank you for sharing all that. I have everything I need to log this episode.",
    "is_followup_required": false,
    "next_question_field": null
}

Remember: Your response will be spoken aloud, so keep it natural and conversational.
PROMPT;
    public function __construct(private readonly ?string $apiKey)
    {
        if (blank($this->apiKey)) {
            throw new RuntimeException('Missing OpenAI API key.');
        }
    }

    public function transcribeAudio(AudioClip $clip): TranscriptionResult
    {
        $disk = Storage::disk(config('filesystems.default'));
        $path = $disk->path($clip->storage_path);

        if (!is_file($path)) {
            throw new RuntimeException("Audio file not found for clip {$clip->id}");
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(120)
            ->retry(3, 1000)
            ->asMultipart()
            ->attach('file', file_get_contents($path), basename($path))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => self::WHISPER_MODEL,
                'response_format' => 'verbose_json',
                'language' => 'en',
            ])
            ->throw()
            ->json();

        $text = Arr::get($response, 'text');
        if (!is_string($text) || $text === '') {
            throw new RuntimeException('Unable to obtain transcript text.');
        }

        $confidence = $this->calculateConfidence(Arr::get($response, 'segments', []));

        return new TranscriptionResult($text, $confidence, self::WHISPER_MODEL);
    }

    public function extractEpisodeData(string $transcript): array
    {
        if (Str::of($transcript)->trim()->isEmpty()) {
            return [];
        }

        // HALLUCINATION GUARD: If the transcript contains known Whisper hallucinations, reject it entirely.
        $hallucinations = [
            'MBC News', 'MBC 뉴스', 'Thank you for watching', 'Thanks for watching',
            'subtitles', 'captioned', 'Amara.org', 'TED',
            'I am a transcription system', 'Only transcribe user speech'
        ];
        
        foreach ($hallucinations as $badPhrase) {
            if (stripos($transcript, $badPhrase) !== false) {
                \Log::warning("Rejected hallucinated transcript: {$transcript}");
                return [];
            }
        }

        // NON-ENGLISH SCRIPT GUARD: Reject if Devanagari, Cyrillic, Arabic, or CJK is detected.
        // This stops "प्रस्तुत्र", "MBC 뉴스", etc. from being processed.
        if (preg_match('/[\x{0900}-\x{097F}\x{0400}-\x{04FF}\x{0600}-\x{06FF}\x{4E00}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $transcript)) {
             \Log::warning("Rejected non-English transcript detected: {$transcript}");
             return [];
        }

        $cacheKey = 'voice_extract_' . md5($transcript);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $prompt = $this->buildExtractionPrompt($transcript);

        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->retry(3, 500)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => self::CHAT_MODEL,
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an assistant that converts migraine voice notes into structured episode data. Respond with JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ])
            ->throw()
            ->json();

        $content = Arr::get($response, 'choices.0.message.content');

            if (!is_string($content) || $content === '') {
                throw new RuntimeException('OpenAI did not return extraction content.');
            }
        
            $decoded = json_decode($content, true);
        
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new RuntimeException('Failed to decode extraction JSON response.');
            }

            // Backend fallback: Improved start_time extraction for common patterns and mis-transcriptions
            if ((empty($decoded['start_time']) || $decoded['start_time'] === null)) {
                $lowerT = strtolower($transcript);
                if (stripos($lowerT, 'this morning') !== false || stripos($lowerT, 'woke up') !== false) {
                    $decoded['start_time'] = now()->format('Y-m-d') . 'T08:00:00';
                } elseif (preg_match('/(\d+|one|two|three|four|five|six|seven|eight|nine|ten)\s+(minutes?|mins?|hours?|hrs?|legal)\s+(ago|before)/i', $lowerT, $sm)) {
                    // We let StructuredEpisodeMapper or the controller handle the precise relative date calculation
                    // but we set a placeholder here so OpenAI knows it's detected.
                    $decoded['start_time'] = 'relative'; 
                } elseif (stripos($lowerT, 'right now') !== false || stripos($lowerT, 'just now') !== false || $lowerT === 'now') {
                    $decoded['start_time'] = now()->toIso8601String();
                }
            }

        $decoded = $this->pruneInvalidTriggers($decoded, $transcript);
        Cache::put($cacheKey, $decoded, 120);
        return $decoded;
    }

    /**
     * Remove triggers that are actually just descriptions of the migraine itself
     * or time references, not actual trigger causes.
     */
    private function pruneInvalidTriggers(array $analysis, string $transcript): array
    {
        $triggers = Arr::get($analysis, 'triggers');
        
        if (!is_array($triggers) || empty($triggers)) {
            return $analysis;
        }

        // Invalid triggers that should never be extracted
        $invalidTriggers = [
            'attack', 'migraine', 'headache', 'pain', 'head',
            'last night', 'yesterday', 'today', 'morning', 'evening',
            'night', 'afternoon', 'time', 'started', 'began'
        ];

        $filteredTriggers = [];
        
        foreach ($triggers as $trigger) {
            if (!is_string($trigger)) {
                continue;
            }

            $normalized = strtolower(trim($trigger));
            
            // Skip if it's an invalid trigger
            if (in_array($normalized, $invalidTriggers, true)) {
                \Log::debug('Pruned invalid trigger:', ['trigger' => $trigger]);
                continue;
            }

            // Keep any trigger not flagged invalid; mapping is handled downstream
            if (!in_array($normalized, $invalidTriggers, true)) {
                $filteredTriggers[] = $normalized;
            }
        }

        if (empty($filteredTriggers)) {
            unset($analysis['triggers']);
            if (isset($analysis['confidence_breakdown']['triggers'])) {
                unset($analysis['confidence_breakdown']['triggers']);
            }
        } else {
            $analysis['triggers'] = array_values(array_unique($filteredTriggers));
        }

        return $analysis;
    }

    private function calculateConfidence(mixed $segments): ?float
    {
        if (!is_array($segments) || $segments === []) {
            return null;
        }

        $probabilities = [];

        foreach ($segments as $segment) {
            $logProb = Arr::get($segment, 'avg_logprob');

            if (is_numeric($logProb)) {
                $probabilities[] = exp((float)$logProb);
            }
        }

        if ($probabilities === []) {
            return null;
        }

        return round(array_sum($probabilities) / count($probabilities), 4);
    }

    private function buildExtractionPrompt(string $transcript): string
    {
        $today = now()->toDateString();
        $timezone = now()->format('P');
        $currentTime = now()->format('Y-m-d H:i:s');
        $yesterday = now()->subDay()->toDateString();

        return <<<PROMPT
You are extracting migraine episode data from a voice transcript. Be EXTREMELY CONSERVATIVE - only extract information that is EXPLICITLY stated.

CRITICAL RULES:
1. ONLY extract what the user EXPLICITLY mentions - NO guessing, NO inference
2. Words like "attack", "migraine", "headache" are NOT triggers - they describe the condition itself
3. **AURA SPECIAL RULE**: If user mentions "aura", you MUST extract it in BOTH places:
   - Set "aura" field to true
   - Add "aura" to the "symptoms" array
4. For triggers: ONLY extract if user says "triggered by", "caused by", "because of","due to" or explicitly mentions: stress, food, weather, sleep, hormones, light, screen time, sound, dehydration
   - **LIGHT SENSITIVITY SPECIAL RULE**: If user mentions "sensitivity to" followed by light-related words (glare, light, bright, sun), extract "light" as trigger. Examples:
     - "sensitivity to glare" → light
     - "glare is blinding" → light
     - "brightness sensitivity" → light
5. For start_time: Extract ANY time reference like "last night 9pm", "yesterday morning", "3 hours ago", "since 7 am", OR "this morning", "this afternoon", "tonight"
   - **CRITICAL**: "this morning", "this afternoon", "this evening", "tonight" are VALID start_time indicators and MUST be extracted
   - **SPECIAL HANDLING FOR INCOMPLETE PHRASES**: If user only says "since" with incomplete time, return null (don't guess the time)
   - "since [TIME]" phrases indicate when the migraine started, so extract the time (e.g., "since 7 am" → 7:00 AM today)
   - "last night" = {$yesterday} at 22:00 (EXTRACT THIS)
   - "last night at 9pm" = {$yesterday} at 21:00 (EXTRACT THIS)
   - "yesterday afternoon" = {$yesterday} at 14:00 (EXTRACT THIS)
   - "this morning" = {$today} at 09:00 (MUST EXTRACT - do not skip)
   - "this morning 7am" = {$today} at 07:00 (use the specific time mentioned, not 09:00)
   - "since 7 am" = {$today} at 07:00 (extract time from "since" phrases)
   - "this afternoon" = {$today} at 14:00 (MUST EXTRACT - do not skip)
   - "tonight" = {$today} at 20:00 (EXTRACT THIS)
6. **INTENSITY CONTEXT RULE**: When asked "On a scale of 1 to 10", if user says "about 9", "it's 7", "around 5", extract the NUMBER as intensity. Also convert:
   - Ordinals: "sixth" → 6, "seventh" → 7, "eighth" → 8, "ninth" → 9
   - Words: "seven" → 7, "eight" → 8, "nine" → 9
   - Adjectives: "severe" → 8, "very severe" → 8-9, "unbearable" → 10, "excruciating" → 10, "splitting" → 9, "pounding" → 7, "throbbing" → 6, "moderate" → 5, "mild" → 2
   - Don't confuse with time references.
7. For pain_location: Extract if user mentions:
   - left/left side/left temple
   - right/right side/right temple
   - back/back of head/back of my head/back of the head/neck/occipital
   - front/forehead/temple (also catches "for head", "fore head" from speech-to-text errors)
   - both/both sides/bilateral/whole head/entire head
   - "of my head" or "of the head" (speech-to-text often drops "back" → treat as occipital/back of head)
   - NOTE: "for headache" is usually a mis-transcription of "forehead" - normalize and extract as frontal
8. For symptoms: ONLY extract if user mentions: nausea, vomiting, aura, light sensitivity, sound sensitivity, dizziness, blurred vision
   - Do NOT infer symptoms from pain location (e.g. "temple" is pain location, not a symptom)
   - Do NOT return "none" or "no symptoms" - if none mentioned, return null
9. If you're not 100% certain, set the field to null

VALID TRIGGERS (only these):
- stress (if user says "stress", "stressed", "stressful", "anxiety")
- food (if user says "food", "ate", "meal", "diet", "chocolate", "cheese", "sugar")
- weather (if user says "weather", "barometric", "storm", "pressure", "rain")
- sleep (if user says "sleep", "lack of sleep", "didn't sleep", "insomnia")
- hormones (if user says "hormones", "period", "cycle", "menstrual")
- light (if user says "light", "bright lights", "sun", "glare", "sensitivity to glare", "brightness", "sensitivity to light", "light sensitivity")
- screen time (if user says "screen", "computer", "phone", "device")
- sound (if user says "sound", "noise", "loud")
- dehydration (if user says "dehydrated", "water", "thirsty", "fluids")

INVALID TRIGGERS (do NOT extract these as triggers):
- "attack" (this describes the migraine itself)
- "migraine" (this is the condition, not a trigger)
- "headache" (this is the condition, not a trigger)
- "pain" (this is a symptom, not a trigger)
- Time references like "last night", "yesterday" (these are for start_time)

TIME PARSING EXAMPLES:
- "last night" → {$yesterday} at 22:00
- "last night 9pm" → {$yesterday} at 21:00
- "last night at 9" → {$yesterday} at 21:00  
- "yesterday night at 9pm" → {$yesterday} at 21:00
- "yesterday morning" → {$yesterday} at 09:00
- "yesterday afternoon" → {$yesterday} at 14:00
- "this morning" → {$today} at 09:00 (if no specific time mentioned)
- "this morning 7am" → {$today} at 07:00 (use the specific time mentioned, not 09:00)
- "this morning at 7" → {$today} at 07:00
- "since 7 am" → {$today} at 07:00 (extract the time even if user says "since")
- "since 7 am in the morning" → {$today} at 07:00 (extract the specific time mentioned)
- "this afternoon" → {$today} at 14:00
- "this afternoon 3pm" → {$today} at 15:00
- "around 10am today" → {$today} at 10:00
- "around 10am" → {$today} at 10:00
- "3 hours ago" → calculate from current time: {$currentTime}

INTENSITY EXTRACTION EXAMPLES:

Numeric answers:
- User says: "Sixth" → intensity: 6, confidence: 0.95
- User says: "Seventh" → intensity: 7, confidence: 0.95
- User says: "It's the sixth" → intensity: 6, confidence: 0.95
- User says: "seven" → intensity: 7, confidence: 0.95
- User says: "about 8" → intensity: 8, confidence: 0.95
- User says: "9 out of 10" → intensity: 9, confidence: 0.95

Descriptive answers (map to scale):
- User says: "mild" → intensity: 2, confidence: 0.95
- User says: "moderate" → intensity: 5, confidence: 0.95
- User says: "severe" → intensity: 8, confidence: 0.95
- User says: "very severe" → intensity: 8-9, confidence: 0.95
- User says: "it's very severe" → intensity: 8-9, confidence: 0.95
- User says: "unbearable" → intensity: 10, confidence: 0.95
- User says: "excruciating" → intensity: 10, confidence: 0.95
- User says: "splitting" → intensity: 9, confidence: 0.95
- User says: "pounding" → intensity: 7, confidence: 0.95
- User says: "throbbing" → intensity: 6, confidence: 0.95

Return JSON with these fields (set to null if not explicitly mentioned):
{
  "start_time": "ISO 8601 UTC timestamp or null",
  "intensity": "integer 0-10 or null",
  "pain_location": "left|right|bilateral|frontal|occipital|other or null",
  "aura": "true|false|null",
  "symptoms": ["array of symptom strings"] or null,
  "triggers": ["array of trigger strings"] or null,
  "notes": "brief summary or null",
  "confidence_breakdown": {
    "start_time": 0.9,
    "intensity": 0.85,
    "pain_location": 0.9,
    "triggers": 0.7,
    "symptoms": 0.8
  }
}

CONFIDENCE SCORES GUIDANCE:
- Use 0.9-1.0 for explicit, unambiguous mentions (e.g., "back of my head" for pain_location)
- Use 0.7-0.89 for clear but less specific mentions (e.g., just "back" for pain_location)
- Use 0.5-0.69 for ambiguous or partially explicit mentions
- Use 0.0-0.49 for very unclear or questionable extractions
- Set to null/0 if not mentioned

Reference date: {$today} (timezone {$timezone})
Current time: {$currentTime}

Transcript:
"""{$transcript}"""

Remember: Be EXTREMELY conservative. If in doubt, set to null. Better to ask the user than to guess incorrectly.
PROMPT;
    }

    /**
     * Synthesize natural-sounding speech for assistant prompts using
     * OpenAI's text-to-speech API. Returns base64-encoded audio data
     * (MP3) suitable for playback in the browser.
     */
    public function synthesizeSpeech(string $text, string $voice = 'nova'): string
    {
        if (Str::of($text)->trim()->isEmpty()) {
            throw new RuntimeException('Cannot synthesize empty speech text.');
        }

        $audioBinary = Http::withToken($this->apiKey)
            ->timeout(30)
            ->retry(3, 300)
            ->withHeaders([
                'Accept' => 'audio/mpeg',
            ])
            ->post('https://api.openai.com/v1/audio/speech', [
                'model' => self::TTS_MODEL,
                'input' => $text,
                'voice' => $voice,
            ])
            ->throw()
            ->body();

        return base64_encode($audioBinary);
    }

    public function createRealtimeSessionToken(array $options = []): array
    {
        $payload = [
            'model' => self::REALTIME_MODEL,
            'modalities' => ['audio', 'text'],
            'turn_detection' => [
                'type' => 'server_vad',
                'threshold' => 0.6,
                'prefix_padding_ms' => 300,
                'silence_duration_ms' => 800,
            ],
        ];

        // If a prompt ID is provided, use it for prompt management
        if (!empty($options['prompt_id'])) {
            $payload['prompt'] = [
                'id' => $options['prompt_id'],
            ];
            
            if (!empty($options['prompt_version'])) {
                $payload['prompt']['version'] = $options['prompt_version'];
            }
        } else {
            // Fallback to manual configuration if no prompt ID (legacy or custom overrides)
            $payload = array_merge($payload, [
                'voice' => $options['voice'] ?? 'nova',
                'input_audio_format' => 'pcm16',
                'instructions' => $options['instructions'] ?? "You are a helpful assistant.",
            ]);
        }

        return Http::withToken($this->apiKey)
            ->withHeaders([
                'OpenAI-Beta' => 'realtime=v1',
            ])
            ->timeout(15)
            ->retry(3, 300)
            ->post('https://api.openai.com/v1/realtime/sessions', $payload)
            ->throw()
            ->json();
    }

    public function generateVoiceAssistantResponse(
        string $transcript,
        array $collectedFields,
        array $missingFields,
        array $structuredData = [],
        array $provisionalFields = []
    ): array {
        $context = $this->buildVoiceAssistantContext($collectedFields, $missingFields, $structuredData, $provisionalFields);

        $messages = [
            ['role' => 'system', 'content' => self::VOICE_ASSISTANT_PROMPT],
            [
                'role' => 'user',
                'content' => <<<PROMPT
Here is the current migraine log context:
{$context}

If a field is marked provisional, confirm it briefly before asking about other missing fields.

User's transcript:
"""{$transcript}"""

Based on the missing/provisional fields, generate your follow-up question. Remember to respond in JSON format.
PROMPT,
            ],
        ];

    try {
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->retry(3, 500)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => self::CHAT_MODEL,
                'temperature' => 0.6,
                'response_format' => ['type' => 'json_object'],
                'messages' => $messages,
                // Short, cost-efficient responses (1–2 sentences max)
                'max_tokens' => 180,
            ])
            ->throw()
            ->json();

        $content = Arr::get($response, 'choices.0.message.content');

        \Log::debug('OpenAI Voice Assistant Raw Response:', [
            'content' => $content,
            'context' => $context,
        ]);

        if (!is_string($content) || $content === '') {
            \Log::warning('Empty response from OpenAI for voice assistant');
            return $this->getFallbackResponse($missingFields);
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            \Log::warning('Invalid JSON from OpenAI:', ['content' => $content]);
            return $this->getFallbackResponse($missingFields);
        }

        // Ensure required fields exist
        if (empty($decoded['assistant_response']) && !empty($missingFields)) {
            \Log::warning('Missing assistant_response in OpenAI response:', ['decoded' => $decoded]);
            return $this->getFallbackResponse($missingFields);
        }

        return [
            'assistant_response' => $decoded['assistant_response'] ?? null,
            'is_followup_required' => $decoded['is_followup_required'] ?? !empty($missingFields),
            'next_question_field' => $decoded['next_question_field'] ?? ($missingFields[0] ?? null),
            'provisional_fields' => $provisionalFields,
        ];

    } catch (\Exception $e) {
        \Log::error('OpenAI Voice Assistant Error:', [
            'message' => $e->getMessage(),
            'transcript' => $transcript,
        ]);
        
        return $this->getFallbackResponse($missingFields);
    }
}

/**
 * Fallback response when OpenAI fails or returns invalid data
 */
private function getFallbackResponse(array $missingFields): array
{
    if (empty($missingFields)) {
        return [
            'assistant_response' => "Thank you. I have all the information I need.",
            'is_followup_required' => false,
            'next_question_field' => null,
            'provisional_fields' => [],
        ];
    }

    $fieldQuestions = [
        'start_time' => "When did this migraine start?",
        'triggers' => "What do you think triggered this migraine?",
        'intensity' => "On a scale of 1 to 10, how intense is the pain?",
        'pain_location' => "Where exactly do you feel the pain?",
        'symptoms' => "What other symptoms are you experiencing?",
    ];

    $nextField = $missingFields[0];
    $question = $fieldQuestions[$nextField] ?? "Can you tell me more about your migraine?";

    return [
        'assistant_response' => $question,
        'is_followup_required' => true,
        'next_question_field' => $nextField,
        'provisional_fields' => [],
    ];
}

    private function buildVoiceAssistantContext(array $collectedFields, array $missingFields, array $structuredData, array $provisionalFields = []): string
    {
        $collectedLines = [];
        foreach ($collectedFields as $field) {
            $value = Arr::get($structuredData, $field);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $label = self::FIELD_LABELS[$field] ?? $field;
            $collectedLines[] = sprintf('%s: %s', $label, $this->stringifyFieldValue($value));
        }

        if ($collectedLines === []) {
            $collectedLines[] = 'None yet';
        }

        $missingLines = $missingFields === []
            ? ['None – ready to save']
            : array_map(
                static fn ($field) => self::FIELD_LABELS[$field] ?? $field,
                $missingFields
            );

        $provisionalLines = [];
        foreach ($provisionalFields as $field => $meta) {
            $label = self::FIELD_LABELS[$field] ?? $field;
            $value = $this->stringifyFieldValue($meta['value'] ?? '');
            $confidence = $meta['confidence'] ?? null;
            $confText = is_numeric($confidence) ? sprintf(' (confidence %.2f)', (float) $confidence) : '';
            $provisionalLines[] = sprintf('%s: %s%s', $label, $value, $confText);
        }

        if ($provisionalLines === []) {
            $provisionalLines[] = 'None';
        }

        $notes = Arr::get($structuredData, 'notes');
        $notesSection = $notes ? "\n\nNotes:\n- {$notes}" : '';

        return sprintf(
            "Collected so far:\n- %s\n\nStill missing:\n- %s\n\nProvisional (needs confirmation):\n- %s%s",
            implode("\n- ", $collectedLines),
            implode("\n- ", $missingLines),
            implode("\n- ", $provisionalLines),
            $notesSection
        );
    }

    private function stringifyFieldValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(static fn ($item) => (string)$item, $value));
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        return (string)$value;
    }
}
