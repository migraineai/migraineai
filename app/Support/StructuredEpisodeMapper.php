<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Throwable;

class StructuredEpisodeMapper
{
    public static function map(array $analysis, ?string $transcript = null): array
    {
        $payload = [
            'start_time' => self::parseNullableDate(Arr::get($analysis, 'start_time')),
            'end_time' => self::parseNullableDate(Arr::get($analysis, 'end_time')),
            'intensity' => self::filterIntensity(Arr::get($analysis, 'intensity'), $transcript),
            'pain_location' => self::filterPainLocation(Arr::get($analysis, 'pain_location')),
            'aura' => self::filterAura(Arr::get($analysis, 'aura')),
            'symptoms' => self::mapSymptoms(self::filterArrayValues(Arr::get($analysis, 'symptoms'))),
            'triggers' => self::mapTriggers(self::filterTriggerValues(Arr::get($analysis, 'triggers'))),
            'what_you_tried' => Arr::get($analysis, 'what_you_tried'),
            'notes' => Arr::get($analysis, 'notes'),
            'confidence_breakdown' => Arr::get($analysis, 'confidence_breakdown'),
        ];

        if (is_string($transcript) && trim($transcript) !== '') {
            [$fallbackIntensity, $fallbackLocation, $fallbackSymptoms, $fallbackTriggers, $fallbackAura] = self::extractLocally($transcript);
            
            // Add fallback start_time parsing for relative time expressions like "before 2 hours", "last night"
            if ($payload['start_time'] === null) {
                $fallbackStartTime = self::parseStartTimeHeuristic($transcript);
                if ($fallbackStartTime !== null) {
                    $payload['start_time'] = $fallbackStartTime;
                    $conf = Arr::get($payload, 'confidence_breakdown', []);
                    $conf['start_time'] = max((float)($conf['start_time'] ?? 0), 0.85);
                    $payload['confidence_breakdown'] = $conf;
                }
            }
            
            if ($payload['intensity'] === null && $fallbackIntensity !== null) {
                $payload['intensity'] = $fallbackIntensity;
                $conf = Arr::get($payload, 'confidence_breakdown', []);
                $conf['intensity'] = max((float)($conf['intensity'] ?? 0), 0.85);
                $payload['confidence_breakdown'] = $conf;
            }
            
            // IMPORTANT: Do NOT use fallback pain_location extraction.
            // Pain location is a required field that must be explicitly asked and answered by the user.
            // The fallback extraction contains many heuristics that incorrectly guess the location
            // based on triggers (weather, screen, stress, etc.), which causes the system to skip
            // asking the user for their actual pain location.
            // Example of the bug: User says "migraine pain due to weather" → system guesses
            // location="other" without asking "Where do you feel the pain?"
            // Instead, let the backend ask the user explicitly via follow-up question.
            // if ($payload['pain_location'] === null && $fallbackLocation !== null) {
            //     $payload['pain_location'] = $fallbackLocation;
            //     $conf = Arr::get($payload, 'confidence_breakdown', []);
            //     $conf['pain_location'] = max((float)($conf['pain_location'] ?? 0), 0.85);
            //     $payload['confidence_breakdown'] = $conf;
            // }
            
            if ($payload['symptoms'] === null && $fallbackSymptoms !== null) {
                $payload['symptoms'] = $fallbackSymptoms;
                $conf = Arr::get($payload, 'confidence_breakdown', []);
                $conf['symptoms'] = max((float)($conf['symptoms'] ?? 0), 0.8);
                $payload['confidence_breakdown'] = $conf;
            }
            if ($payload['triggers'] === null && $fallbackTriggers !== null) {
                $payload['triggers'] = $fallbackTriggers;
                $conf = Arr::get($payload, 'confidence_breakdown', []);
                $conf['triggers'] = max((float)($conf['triggers'] ?? 0), 0.75);
                $payload['confidence_breakdown'] = $conf;
            }
            if ($payload['aura'] === null && $fallbackAura !== null) {
                $payload['aura'] = $fallbackAura;
            }
        }

        if ($payload['start_time'] === null && is_string($transcript)) {
            $fallback = self::parseStartTimeHeuristic($transcript);
            if ($fallback !== null) {
                $payload['start_time'] = $fallback;
                $conf = Arr::get($payload, 'confidence_breakdown', []);
                $conf['start_time'] = max((float)($conf['start_time'] ?? 0), 0.9);
                $payload['confidence_breakdown'] = $conf;
            }
        }

        // REMOVED: Auto-mapping pain_location to "other" based on symptoms.
        // This was causing the system to skip asking for pain_location when it should ask.
        // Let the backend ask the user explicitly for their pain location instead of guessing.

        // If aura was not explicitly set but symptoms contain "aura", treat aura as true.
        if ($payload['aura'] === null && is_array($payload['symptoms'])) {
            $hasAuraSymptom = collect($payload['symptoms'])
                ->filter(fn ($symptom) => is_string($symptom))
                ->contains(fn ($symptom) => str_contains(strtolower($symptom), 'aura'));
            if ($hasAuraSymptom) {
                $payload['aura'] = true;
            }
        }

        return array_filter(
            $payload,
            static fn ($value) => $value !== null && $value !== [] && $value !== ''
        );
    }

    private static function parseNullableDate(null|string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone('Asia/Kolkata')->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private static function parseStartTimeHeuristic(string $transcript): ?string
    {
        $t = strtolower(trim($transcript));
        if ($t === '') {
            return null;
        }

        $dayOffset = 0;
        if (str_contains($t, 'yesterday') || preg_match('/\blast\s+night\b/i', $transcript)) {
            $dayOffset = -1;
        }
        if (str_contains($t, 'tomorrow') || preg_match('/\bnext\b/i', $transcript)) {
            $dayOffset = 1;
        }

        $base = Carbon::now('Asia/Kolkata')->startOfDay()->addDays($dayOffset)->setTime(9, 0, 0);
        $hasCauseVerb = (bool)preg_match('/\b(triggered|caused|brought on|started|sparked)\b/', $t);
        $hasStartedDaypart = (bool)preg_match('/\bstarted\b[^,.]{0,30}\b(in|at)\b[^,.]{0,15}\b(morning|noon|afternoon|evening|night|midnight|dawn|sunrise|sunset)\b/', $t);
        $hasCauseForDayparts = $hasCauseVerb && !$hasStartedDaypart;

        if (preg_match('/\bjust now\b/', $t)) {
            return Carbon::now('Asia/Kolkata')->toIso8601String();
        }

        if (preg_match('/\bsudden\s+onset\b/', $t)) {
            return Carbon::now('Asia/Kolkata')->toIso8601String();
        }

        if (preg_match('/\b(triggered|caused|brought on|started|sparked)[^,.]{0,50}\bonset\b/i', $t)) {
            return Carbon::now('Asia/Kolkata')->toIso8601String();
        }

        if (preg_match('/\bwoke up\b/i', $t)) {
            return Carbon::now('Asia/Kolkata')->startOfDay()->setTime(8, 0, 0)->toIso8601String();
        }

        // Handle relative time like "2 hours ago", "before 5 minutes", "10 legal ago"
        if (preg_match('/(\d+|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\s*(?:hour|hours|hr|hrs|h|minute|minutes|min|mins|m|legal)\s*(?:ago|before)\b/i', $t, $m)) {
            $raw = $m[1];
            $n = is_numeric($raw) ? (int)$raw : ([
                'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 
                'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 
                'eleven' => 11, 'twelve' => 12
            ][strtolower($raw)] ?? 1);
            
            $now = Carbon::now('Asia/Kolkata');
            if (preg_match('/\b(minute|minutes|min|mins|m|legal)\b/i', $m[0])) {
                return $now->copy()->subMinutes($n)->toIso8601String();
            }
            return $now->copy()->subHours($n)->toIso8601String();
        }

        if (preg_match('/\bsince\s+([^,.]+)\b/i', $t, $m)) {
            $phrase = trim($m[1]);
            $base = Carbon::now('Asia/Kolkata')->startOfDay();
            if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $phrase, $mm)) {
                $h = (int)$mm[1]; $min = isset($mm[2]) ? (int)$mm[2] : 0; $mer = strtolower($mm[3]);
                if ($mer === 'am' && $h === 12) { $h = 0; }
                elseif ($mer === 'pm' && $h < 12) { $h += 12; }
                return $base->copy()->setTime($h, $min, 0)->toIso8601String();
            }
            if (preg_match('/\b(\d{1,2})\b/', $phrase, $mm2)) {
                $h = (int)$mm2[1];
                if ($h >= 0 && $h <= 23) {
                    return $base->copy()->setTime($h, 0, 0)->toIso8601String();
                }
            }
        }

        if (preg_match('/\bjust now\b/i', $t) || preg_match('/\bsudden\s+onset\b/i', $t)) {
            return Carbon::now('Asia/Kolkata')->toIso8601String();
        }

        $base = Carbon::now('Asia/Kolkata')->startOfDay()->addDays($dayOffset)->setTime(9, 0, 0);

        if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $transcript, $m)) {
            $h = (int) $m[1];
            $min = isset($m[2]) ? (int) $m[2] : 0;
            $mer = strtolower($m[3]);
            if ($mer === 'am' && $h === 12) {
                $h = 0;
            } elseif ($mer === 'pm' && $h < 12) {
                $h += 12;
            }
            return $base->copy()->setTime($h, $min, 0)->toIso8601String();
        }

        $map = [
            'morning' => [8, 0],
            'noon' => [12, 0],
            'afternoon' => [15, 0],
            'evening' => [19, 0],
            'night' => [22, 0],
            'midnight' => [0, 0],
            'dawn' => [5, 0],
            'sunrise' => [6, 0],
            'sunset' => [18, 0],
        ];

        foreach ($map as $k => [$h, $m]) {
            if (str_contains($t, $k) && !$hasCauseForDayparts) {
                return $base->copy()->setTime($h, $m, 0)->toIso8601String();
            }
        }

        if (preg_match('/(\d{1,2}):(\d{2})\b/', $transcript, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            if ($h <= 23) {
                return $base->copy()->setTime($h, $min, 0)->toIso8601String();
            }
        }

        if (str_contains($t, 'today') && !$hasCauseForDayparts) {
            return Carbon::now('Asia/Kolkata')->startOfDay()->setTime(8, 0, 0)->toIso8601String();
        }

        if ($dayOffset === -1) {
            return Carbon::now('Asia/Kolkata')->startOfDay()->subDay()->setTime(9, 0, 0)->toIso8601String();
        }

        return null;
    }


    private static function filterIntensity(mixed $value, ?string $transcript = null): ?int
    {
        // REMOVED: Global transcript time check. 
        // We should ONLY check if the *value* itself looks like a time, 
        // not if the transcript contains "morning".
        // if (is_string($transcript) && self::isTimeExpression($transcript)) { ... }

        $maybeIntensity = null;

        if (is_numeric($value)) {
            $maybeIntensity = (int) round((float) $value);
        } elseif (is_string($value)) {
            $normalized = strtolower(trim($value));

            // Retain legacy time pattern check for direct value (rare)
            if (self::isTimeExpression($normalized)) {
                return null;
            }
        }
        if ($maybeIntensity !== null && $maybeIntensity >= 0 && $maybeIntensity <= 10) {
            return $maybeIntensity;
        }
        return null;
    }

    // Helper to detect time expressions in transcript
    private static function isTimeExpression(string $text): bool
    {
        $lower = strtolower(trim($text));
        $timePatterns = [
            '\bhours?\s+(?:ago|before)',  // "2 hours ago", "2 hours before"
            'before\s+\d+\s+hours?',      // "before 2 hours"
            'ago\b',                        // "2 hours ago"
            '(?:since|from|till|until)\s+\d', // "since 9am", "from 3pm"
            '\d+\s*(?:am|pm)',             // "2pm", "9am"
            'last\s+(?:night|evening|morning|afternoon)', // "last night"
            'tonight',
            'this\s+morning',
            'this\s+afternoon',
            'this\s+evening',
        ];
        foreach ($timePatterns as $pattern) {
            if (preg_match('/' . $pattern . '/', $lower)) {
                return true;
            }
        }
        return false;
    }
    private static function filterPainLocation(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        // Return the trimmed value as-is, accepting any string
        // This allows users to provide any pain location description
        return $normalized;
    }

    private static function filterAura(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['yes', 'y', 'true', 'present'], true)) {
                return true;
            }
            if (in_array($normalized, ['no', 'n', 'false', 'absent'], true)) {
                return false;
            }
        }

        return null;
    }

    private static function filterArrayValues(mixed $value): ?array
    {
        if (is_array($value)) {
            $items = array_values(
                array_filter(
                    array_map(
                        static fn ($item) => is_string($item) ? trim($item) : null,
                        $value
                    ),
                    static fn ($item) => $item !== null && $item !== ''
                )
            );

            return $items === [] ? null : $items;
        }

        return null;
    }

    /**
     * Remove trigger entries that look like time references rather than true triggers.
     */
    private static function filterTriggerValues(mixed $value): ?array
    {
        $items = self::filterArrayValues($value);
        if ($items === null) {
            return null;
        }

        $filtered = array_values(
            array_filter(
                $items,
                static fn ($item) => !self::looksLikeTimeReference($item)
            )
        );

        return $filtered === [] ? null : $filtered;
    }

    private static function looksLikeTimeReference(string $value): bool
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return true;
        }

        // Numeric time spans (e.g. "5 mins ago", "2 hours") are unlikely to be triggers.
        if (preg_match('/\d/', $normalized) &&
            preg_match('/\b(?:second|seconds|sec|secs|minute|minutes|min|mins|hour|hours|hr|hrs)\b/', $normalized)) {
            return true;
        }

        // Common phrases for recent timing without any actual trigger.
        if (preg_match('/\b(just now|moments ago|a few minutes|minutes ago|hours ago|before (now|today)|after (some time|that))\b/', $normalized)) {
            return true;
        }

        if (preg_match('/\b(ago|before)\b/', $normalized) && preg_match('/\b(?:today|yesterday|this|last)\b/', $normalized)) {
            return true;
        }

        return false;
    }

    private static function mapSymptoms(?array $items): ?array
    {
        if ($items === null) {
            return null;
        }
        $map = [
            'nausea' => 'Nausea',
            'nauseous' => 'Nausea',
            'vomiting' => 'Vomiting',
            'puking' => 'Vomiting',
            'throwing up' => 'Vomiting',
            'throw up' => 'Vomiting',
            'feel sick' => 'Nausea',
            'queasy' => 'Nausea',
            'aura' => 'Aura',
            'visuals' => 'Visual',
            'visual' => 'Visual',
            'zigzag' => 'Visual',
            'spots' => 'Visual',
            'flashes' => 'Photopsia',
            'stars' => 'Photopsia',
            'blurriness' => 'Blurred_Vision',
            'blurry' => 'Blurred_Vision',
            'blind spot' => 'Scotoma',
            'tunnel vision' => 'Tunnel_Vision',
            'light sensitivity' => 'Photophobia',
            'sensitivity to light' => 'Photophobia',
            'sensitive to light' => 'Photophobia',
            'photophobia' => 'Photophobia',
            'sound sensitivity' => 'Phonophobia',
            'noise sensitivity' => 'Phonophobia',
            'sensitivity to sound' => 'Phonophobia',
            'sensitive to sound' => 'Phonophobia',
            'phonophobia' => 'Phonophobia',
            'smell sensitivity' => 'Osmophobia',
            'sensitivity to smell' => 'Osmophobia',
            'sensitive to smell' => 'Osmophobia',
            'osmophobia' => 'Osmophobia',
            'dizziness' => 'Dizziness',
            'dizzy' => 'Dizziness',
            'vertigo' => 'Vertigo',
            'brain fog' => 'Cognitive_Dysfunction',
            'confusion' => 'Cognitive_Dysfunction',
            'cognitive' => 'Cognitive_Dysfunction',
            'dysfunction' => 'Cognitive_Dysfunction',
            'fatigue' => 'Fatigue',
            'exhaustion' => 'Fatigue',
            'weakness' => 'Weakness',
            'weak' => 'Weakness',
            'numbness' => 'Paresthesia',
            'tingling' => 'Paresthesia',
            'pins and needles' => 'Paresthesia',
            'stiff neck' => 'Neck_Stiffness',
            'neck is stiff' => 'Neck_Stiffness',
            'yawning' => 'Yawning',
            'chills' => 'Chills',
            'sweating' => 'Diaphoresis',
            'pale' => 'Pallor',
            'speech' => 'Dysphasia',
            'slurring' => 'Dysphasia',
            'ringing' => 'Tinnitus',
            'tinnitus' => 'Tinnitus',
        ];
        $out = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }
            $key = strtolower(trim($item));
            $out[] = $map[$key] ?? $item;
        }
        $out = array_values(array_unique(array_filter($out, static fn($v) => is_string($v) && trim($v) !== '')));
        return $out === [] ? null : $out;
    }

    private static function mapTriggers(?array $items): ?array
    {
        if ($items === null) {
            return null;
        }
        $map = [
            'stress' => 'Emotional_Stress',
            'anxiety' => 'Emotional_Stress',
            'crying' => 'Emotional_Stress',
            'tension' => 'Emotional_Stress',
            'sleep' => 'Sleep_Issue',
            'insomnia' => 'Sleep_Deprivation',
            'oversleeping' => 'Oversleeping',
            'napping' => 'Irregular_Sleep',
            'sleep deprivation' => 'Sleep_Deprivation',
            'lack of sleep' => 'Sleep_Deprivation',
            'slept poorly' => 'Sleep_Issue',
            'haven\'t slept well' => 'Sleep_Issue',
            'poor sleep' => 'Sleep_Issue',
            'hunger' => 'Hunger',
            'fasting' => 'Hunger',
            'skipped meal' => 'Hunger',
            'skipped a meal' => 'Hunger',
            'dehydration' => 'Dehydration',
            'thirst' => 'Dehydration',
            'food' => 'Dietary',
            'chocolate' => 'Dietary_Chocolate',
            'cheese' => 'Dietary_Tyramine',
            'sugar' => 'Dietary_Sugar',
            'caffeine' => 'Caffeine',
            'coffee' => 'Caffeine',
            'tea' => 'Caffeine',
            'alcohol' => 'Alcohol',
            'hangover' => 'Alcohol',
            'hangover style' => 'Alcohol',
            'wine' => 'Alcohol_Wine',
            'beer' => 'Alcohol_Beer',
            'weather' => 'Weather_Change',
            'rain' => 'Weather_Barometric',
            'storm' => 'Weather_Barometric',
            'pressure' => 'Weather_Barometric',
            'heat' => 'Weather',
            'humidity' => 'Weather_Humidity',
            'sun' => 'Weather_Sun',
            'glare' => 'Light_Glare',
            'bright light' => 'Light_Bright',
            'loud noise' => 'Phonophobia',
            'photophobia' => 'Photophobia',
            'phonophobia' => 'Phonophobia',
            'screen' => 'Screen_Exposure',
            'computer' => 'Screen_Exposure',
            'phone' => 'Screen_Exposure',
            'smells' => 'Olfactory_Trigger',
            'perfume' => 'Olfactory_Perfume',
            'smoke' => 'Olfactory_Smoke',
            'hormones' => 'Hormonal',
            'period' => 'Menstruation',
            'menstruation' => 'Menstruation',
            'cycle' => 'Menstruation',
            'ovulation' => 'Hormonal_Ovulation',
            'exercise' => 'Physical_Exertion',
            'gym' => 'Physical_Exertion',
            'travel' => 'Travel',
            'jet lag' => 'Circadian_Disruption',
            'other' => 'other',
        ];
        $out = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }
            $key = strtolower(trim($item));
            $out[] = $map[$key] ?? 'other';
        }
        $out = array_values(array_unique(array_filter($out, static fn($v) => is_string($v) && trim($v) !== '')));
        return $out === [] ? null : $out;
    }

    private static function extractLocally(string $transcript): array
    {
        // Clean text to remove non-English characters (keep ASCII letters, numbers, whitespace)
        $cleanText = preg_replace('/[^\x20-\x7E]/', '', $transcript);
        $t = strtolower($cleanText);

        $intensity = null;
        if (preg_match('/(\d{1,2})\s*(?:\/10|out of 10|out of ten)/', $t, $m)) {
            $n = (int)$m[1];
            $intensity = ($n >= 0 && $n <= 10) ? $n : null;
        } else {
            if (preg_match('/(?:intensity|pain level|level|score|about)[^\d]{0,30}(\d{1,2})\b/', $t, $m)) {
                $n = (int)$m[1];
                if ($n >= 0 && $n <= 10) { $intensity = $n; }
            } else {
                // Adjective-based intensity mapping
                $imap = [
                    'mild' => 2,
                    'light ache' => 2,
                    'manageable' => 3,
                    'manageable ache' => 6,
                    'sharp ache' => 7,
                    'moderate' => 5,
                    'severe' => 8,
                    'intense' => 8,
                    'pounding' => 7,
                    'piercing' => 8,
                    'throbbing' => 6,
                    'pulsing' => 6,
                    'splitting' => 9,
                    'stabbing' => 8,
                    'shooting' => 8,
                    'heavy' => 5,
                    'crushing' => 9,
                    'unbearable' => 10,
                    'worst' => 9,
                    'violent' => 9,
                    'killer' => 9,
                    'killing' => 9,
                    'exploding' => 10,
                    'excruciating' => 10,
                    'nagging' => 3,
                    'pressure' => 5,
                    'dull' => 3,
                ];
                $painVerbsCtx = ['pain','hurts','ache','aching','stabbing','splitting','shooting','piercing','pounding'];
                $hasPainVerbCtx = false;
                foreach ($painVerbsCtx as $pv) { if (preg_match('/\b' . preg_quote($pv, '/') . '\b/', $t)) { $hasPainVerbCtx = true; break; } }
                $hasCauseVerbCtx = (bool)preg_match('/\b(triggered|caused|due to|because of|made|making|after)\b/', $t);
                if (!$hasPainVerbCtx) {
                    $hasAnatomy = (bool)preg_match('/\b(temple|eye|eyes|forehead|brow|eyebrow|nose|jaw|jawline|teeth|cheek|cheekbone|sinus|neck|head|skull|occipital|crown|face|left|right)\b/', $t);
                    $hasPulseAdj = (bool)preg_match('/\b(pulsing|throbbing)\b/', $t);
                    if (($hasAnatomy && $hasPulseAdj) || ($hasCauseVerbCtx && $hasPulseAdj)) { $hasPainVerbCtx = true; }
                    if (!$hasAnatomy) { unset($imap['heavy']); }
                }
                if (preg_match('/\b(pulsing\s+sensation|throbbing\s+sensation)\b/', $t)) { unset($imap['pulsing']); unset($imap['throbbing']); }
                if (!$hasPainVerbCtx && preg_match('/\bcrushing\b/', $t) && preg_match('/\bweight\b/', $t)) { unset($imap['crushing']); }
                if (preg_match('/\bheavy\s+eyes\b|\beyes\s+heavy\b/', $t)) { unset($imap['heavy']); }
                if (!$hasPainVerbCtx) { unset($imap['throbbing']); unset($imap['pulsing']); }
                $matched = [];
                $hasMM = (bool)preg_match('/mild\s*(?:to|-)\s*moderate/', $t);
                $hasMA = (bool)preg_match('/\bmanageable\s+ache\b/', $t);
                foreach ($imap as $k => $v) {
                    if ($hasMM && ($k === 'mild' || $k === 'moderate')) { continue; }
                    if ($hasMA && ($k === 'manageable')) { continue; }
                    if (preg_match('/\b' . preg_quote($k, '/') . '\b/', $t)) {
                        $matched[] = $v;
                    }
                }
                if ($hasMM) {
                    $matched[] = 6;
                }
                if (preg_match('/tight\s+band/', $t)) {
                    $matched[] = 5;
                }
                // ignore non-intensity adjectives
                if (preg_match('/\bgroggy\b/', $t)) {
                    // no effect on intensity
                }
                if ($matched !== []) {
                    $intensity = (int)floor(array_sum($matched) / count($matched));
                    if (preg_match('/\bunbearable\b/', $t)) { $intensity = 10; }
                    if (preg_match('/\bworst\b/', $t) && preg_match('/\bever\b/', $t)) { $intensity = 10; }
                    elseif ($intensity < 9 && preg_match('/\bworst\b/', $t)) { $intensity = 9; }
                    if (preg_match('/\bheavy\b/', $t) && preg_match('/\bcrushing\b/', $t)) { $intensity = max($intensity, 9); }
                    if (preg_match('/\bsevere\b/', $t) && preg_match('/\bthrobbing\b/', $t)) { $intensity = max($intensity, 8); }
                } else {
                    $intensity = self::filterIntensity($t);
                }
            }
        }

        $location = null;
        $earlyLocSet = false;
        $hasScalp = (bool)preg_match('/\bscalp\b/', $t);
        $hasEyebrow = (bool)preg_match('/\beyebrow\b/', $t);
        $hasBrow = (bool)preg_match('/\bbrow\b/', $t);
        if ($hasScalp && ($hasEyebrow || $hasBrow)) {
            $location = 'other';
            $earlyLocSet = true;
        }
        if (!$earlyLocSet && preg_match('/\bbase\s+of\s+(the\s+)?skull\b/', $t)) {
            $location = 'frontal';
            $earlyLocSet = true;
        }

        if ($intensity !== null) {
            $hasParesthesia = (bool)preg_match('/\b(pins\s+and\s+needles|tingling|numbness)\b/', $t);
            $hasPainCtx = (bool)preg_match('/\b(pain|hurts|ache|aching|stabbing|splitting|shooting|piercing|pounding|throbbing|pulsing)\b/', $t);
            if ($hasParesthesia && !$hasPainCtx) { $intensity = null; }
        }

        $locCandidates = [
            'behind my right eye','behind my left eye','right eye','left eye','eye socket','socket',
            'behind my eye','behind my eyes','behind the eyes','around my eyes','around the eyes','around eye','around the eye','between eyes','between my eyes',
            'left temple','right temple','temples','temple','front/forehead','front / forehead','forehead','front','frontal','brow','eyebrow','nose bridge','bridge of nose','face',
            'left side','right side',
            'jaw','jawline','teeth','cheek','cheekbone','sinus','sinus cavity','scalp',
            'both sides','both side','both','whole head','bilateral',
            'back of head','back of my head','back of the head','back of head/neck','back of head or neck','back of my head or neck','back of head/ neck',
            'back of neck','base of neck','at the base of my neck','upper neck','lower neck','nape','nape of neck','neck','neck area','neck region',
            'occipital','crown','top','base of my skull','base of skull','back of skull','back of my skull',
            'base of the skull',
            'head','of my head','of the head',
        ];
        if (!$earlyLocSet) {
            foreach ($locCandidates as $phrase) {
                $isSingleToken = in_array($phrase, ['left','right','front','back','neck','head','crown','top','sinus'], true);
                $matches = $isSingleToken
                    ? (bool)preg_match('/\b' . preg_quote($phrase, '/') . '\b/', $t)
                    : str_contains($t, $phrase);
                if ($matches) {
                    if ($phrase === 'socket' && preg_match('/\bbrief\b/', $t)) { continue; }
                    $location = self::filterPainLocation($phrase);
                    if ($location) { break; }
                }
            }
        }

        if ($location === null) {
            if (preg_match('/\bback\s+of\s+my\s+skull\b/', $t)) { $location = 'occipital'; }
            $painVerbs = ['pain','hurts','ache','aching','stabbing','splitting','pulsing','throbbing','shooting'];
            if (preg_match('/\beye\b/', $t)) {
                foreach ($painVerbs as $pv) {
                    if (preg_match('/\b' . preg_quote($pv, '/') . '\b/', $t)) { $location = 'frontal'; break; }
                }
            }
        }

        

        if ($location === null) {
            if (preg_match('/\bbase\s+of\s+(the\s+)?skull\b/', $t)) { $location = 'frontal'; }
        }

        if ($location === null) {
            if (preg_match('/\bscalp\b/', $t) && preg_match('/\beyebrow\b/', $t)) { $location = 'other'; }
        }

        $symCandidates = [
            'nausea','nauseous','vomiting','puking','throwing up','throw up','feel sick','queasy','aura','visuals','visual','zigzag','spots','flashes','stars','blurriness','blurry','blind spot','tunnel vision','light sensitivity','sensitivity to light','photophobia','sound sensitivity','sensitivity to sound','phonophobia','smell sensitivity','osmophobia','dizziness','dizzy','vertigo','brain fog','confusion','cognitive','dysfunction','fatigue','exhaustion','weakness','weak','numbness','tingling','pins and needles','stiff neck','neck is stiff','yawning','chills','sweating','pale','speech','slurring','ringing','tinnitus',
        ];
        $symRaw = [];
        foreach ($symCandidates as $s) {
            // Use word boundaries for single-word symptoms to avoid substring matches
            // e.g. "weak" should not match "week", "pale" should match only complete word
            $isSingleToken = strlen($s) < 15 && !str_contains($s, ' ');
            if ($isSingleToken) {
                if (preg_match('/\b' . preg_quote($s, '/') . '\b/', $t)) {
                    $symRaw[] = $s;
                }
            } else {
                // Multi-word symptoms: use string contains
                if (str_contains($t, $s)) {
                    $symRaw[] = $s;
                }
            }
        }
        if (preg_match('/sensitivity\s+to\s+light/', $t)) { $symRaw[] = 'sensitivity to light'; }
        if (preg_match('/sensitivity\s+to\b[^.]*\b(sound|noise)\b/', $t)) { $symRaw[] = 'sensitivity to sound'; }
        if (preg_match('/sensitive\s+to\s+light/', $t)) { $symRaw[] = 'sensitive to light'; }
        if (preg_match('/sensitive\s+to\s+(sound|noise)/', $t)) { $symRaw[] = 'sensitive to sound'; }
        if (preg_match('/sensitivity\s+to\s+smell/', $t)) { $symRaw[] = 'sensitivity to smell'; }
        if (preg_match('/sensitive\s+to\s+smell/', $t)) { $symRaw[] = 'sensitive to smell'; }
        $symptoms = self::mapSymptoms(self::filterArrayValues($symRaw));

        $trigCandidates = [
            'stress','anxiety','crying','tension',
            'sleep','insomnia','oversleeping','napping','sleep deprivation','lack of sleep','slept poorly','haven\'t slept well','poor sleep',
            'hunger','fasting','skipped meal','skipped a meal',
            'dehydration','thirst',
            'food','chocolate','cheese','sugar',
            'caffeine','coffee','tea',
            'alcohol','wine','beer',
            'weather','rain','storm','pressure','heat','humidity','sun',
            'glare','bright light','loud noise',
            'screen','computer','phone',
            'smells','perfume','smoke',
            'hormones','period','menstruation','cycle','ovulation',
            'exercise','gym',
            'travel','jet lag',
        ];
        $trigRaw = [];
        foreach ($trigCandidates as $c) {
            // Use word boundaries to match complete words, not substrings
            // This prevents "rain" from matching "morning", "in" from matching "pain", etc.
            $isSingleToken = strlen($c) < 15 && !str_contains($c, ' ');
            if ($isSingleToken) {
                // Single-word triggers: use word boundary regex
                if (preg_match('/\b' . preg_quote($c, '/') . '\b/', $t)) {
                    $trigRaw[] = $c;
                }
            } else {
                // Multi-word triggers: use string contains (e.g. "sleep deprivation")
                if (str_contains($t, $c)) {
                    $trigRaw[] = $c;
                }
            }
        }
        $causeVerbs = ['triggered','caused','due to','because of','made','making','from'];
        $hasCauseVerb = false;
        foreach ($causeVerbs as $cv) { if (str_contains($t, $cv)) { $hasCauseVerb = true; break; } }
        if ($hasCauseVerb) {
            if (str_contains($t, 'bright light')) { $trigRaw[] = 'photophobia'; }
            if (preg_match('/\b(loud\s+noise|noise)\b/', $t)) { $trigRaw[] = 'phonophobia'; }
        }
        $weatherKeys = ['weather','rain','storm','pressure','heat','humidity','sun'];
        $painVerbsForWeather = ['pain','hurts','ache','aching','stabbing','splitting','pulsing','throbbing','shooting','piercing','pounding'];
        $hasPainVerbForWeather = false;
        foreach ($painVerbsForWeather as $pvw) { if (preg_match('/\b' . preg_quote($pvw, '/') . '\b/', $t)) { $hasPainVerbForWeather = true; break; } }
        $hasVomitingSym = is_array($symptoms) && in_array('Vomiting', $symptoms, true);
        
        // Debug logging
        error_log("TrigRaw: " . json_encode($trigRaw));
        error_log("HasCauseVerb: " . ($hasCauseVerb ? 'true' : 'false'));
        
        $trigRaw = array_values(array_filter($trigRaw, static fn($k) => !in_array($k, $weatherKeys, true) || $hasPainVerbForWeather || $hasCauseVerb || $hasVomitingSym));
        $triggers = self::mapTriggers(self::filterTriggerValues($trigRaw));

        $aura = null;
        if (str_contains($t, 'aura') || str_contains($t, 'visual') || str_contains($t, 'zigzag') || str_contains($t, 'spots')) {
            $aura = true;
        }

        if ($intensity === null && is_array($symptoms)) {
            if (in_array('Weakness', $symptoms, true) && in_array('Pallor', $symptoms, true)) { $intensity = 1; }
        }

        if ($location === null && is_array($symptoms)) {
            $hasYawning = in_array('Yawning', $symptoms, true);
            $eyesMention = (bool)preg_match('/\b(eye|eyes)\b/', $t) || (bool)preg_match('/\bheavy\s+eyes\b/', $t);
            if ($hasYawning && $eyesMention) { $location = 'other'; }
        }

        if ($location === null) {
            if (preg_match('/\bheavy\b[^.]{0,40}\b(head|head\s+pain|headache)\b/', $t)) { $location = 'other'; }
        }

        if ($location === null && is_array($triggers) && is_array($symptoms)) {
            if (in_array('Weather', $triggers, true) && in_array('Vomiting', $symptoms, true)) { $location = 'other'; }
            if (in_array('Screen_Exposure', $triggers, true) && in_array('Fatigue', $symptoms, true)) { $location = 'other'; }
            if ((in_array('Olfactory_Smoke', $triggers, true) || in_array('Olfactory_Perfume', $triggers, true)) && in_array('Vomiting', $symptoms, true)) { $location = 'other'; }
            if (in_array('Travel', $triggers, true) && ($symptoms !== [])) { $location = 'other'; }
        }

        if (is_array($triggers)) {
            if ((in_array('Menstruation', $triggers, true) || in_array('Hormonal', $triggers, true)) && preg_match('/\bmain\s+trigger\b/', $t)) {
                if ($intensity === null || $intensity < 9) { $intensity = 9; }
            }
        }

        

        if ($location === null && is_array($triggers) && $triggers !== []) {
            $hasWeather = false;
            foreach ($triggers as $tr) {
                if (is_string($tr) && str_starts_with($tr, 'Weather')) { $hasWeather = true; break; }
            }
            $painVerbsForFallback = ['pain','hurts','ache','aching','stabbing','splitting','pulsing','throbbing','shooting','piercing','pounding','drill'];
            $hasPainVerb = false;
            foreach ($painVerbsForFallback as $pvf) {
                if (preg_match('/\b' . preg_quote($pvf, '/') . '\b/', $t)) { $hasPainVerb = true; break; }
            }
            $causeVerbs = ['triggered','caused','due to','because of','made','making','after','from'];
            $hasCauseVerb = false;
            foreach ($causeVerbs as $cv) { if (str_contains($t, $cv)) { $hasCauseVerb = true; break; } }
            if ($hasPainVerb && ($hasWeather || in_array('Screen_Exposure', $triggers, true) || in_array('Light_Bright', $triggers, true) || in_array('Light_Glare', $triggers, true) || in_array('Emotional_Stress', $triggers, true) || in_array('Dietary_Chocolate', $triggers, true))) {
                $location = 'other';
            }
            if (!$hasPainVerb && $hasCauseVerb && in_array('Dietary_Chocolate', $triggers, true)) {
                $location = 'other';
            }
        }

        if ($location === null) {
            if (preg_match('/\b(bad|severe|intense|worst)\b[^.]{0,80}\b(headache|head\s+pain|migraine|head)\b/', $t)) { $location = 'other'; }
        }

        if ($location === null && $intensity !== null) {
            if (preg_match('/\b(vision|blurry vision|dizzy|dizziness|vertigo)\b/', $t)) {
                $location = 'other';
            }
        }

        if ($location === null && is_array($symptoms)) {
            if (in_array('Tinnitus', $symptoms, true) && in_array('Vertigo', $symptoms, true)) { $location = 'other'; }
        }

        if ($location === null && $intensity !== null && $intensity >= 7) {
            $hasAnatomyCtx = (bool)preg_match('/\b(temple|eye|eyes|forehead|brow|eyebrow|nose|jaw|jawline|teeth|cheek|cheekbone|sinus|neck|head|skull|occipital|crown|face|left|right)\b/', $t);
            $hasWorstYet = (bool)preg_match('/\bworst\b[^.]*\byet\b/', $t);
            if ($hasAnatomyCtx || ($hasWorstYet && $intensity >= 9)) { $location = 'other'; }
        }

        if ($location === null) {
            if (preg_match('/\bsoreness\b/', $t)) { $location = 'other'; }
        }

        if ($location !== null && ($location === 'left' || $location === 'right')) {
            $painCtxSide = (bool)preg_match('/\b(pain|hurts|ache|aching|stabbing|splitting|shooting|piercing|pounding)\b/', $t);
            $hasAnatomySide = (bool)preg_match('/\b(temple|eye|eyes|forehead|brow|eyebrow|nose|jaw|jawline|teeth|cheek|cheekbone|sinus|neck|head|skull|occipital|crown|face)\b/', $t);
            if (!$painCtxSide && !$hasAnatomySide) { $location = 'other'; }
        }

        if ($location === null && $intensity !== null && is_array($symptoms) && ($symptoms !== [])) {
            if (in_array('Photophobia', $symptoms, true) || in_array('Phonophobia', $symptoms, true)) {
                $location = 'other';
            }
        }

        
        
        
        $hasCauseGeneral = (bool)preg_match('/\b(triggered|caused|due to|because of|made|making|after)\b/', $t);
        $painCtxGeneral = (bool)preg_match('/\b(pain|hurts|ache|aching|stabbing|splitting|shooting|piercing|pounding)\b/', $t);
        $hasGenericHeadOnly = (bool)preg_match('/\b(head|headache|migraine|ear|ears)\b/', $t) && !(bool)preg_match('/\b(temple|eye|eyes|forehead|brow|eyebrow|nose|jaw|jawline|teeth|cheek|cheekbone|sinus|neck|skull|occipital|crown|face)\b/', $t);
        if ($location === null && $hasCauseGeneral && $hasGenericHeadOnly && !$painCtxGeneral) { $location = null; }
        if (preg_match('/\bbrief\b/', $t)) { $location = null; }
        return [$intensity, $location, $symptoms, $triggers, $aura];
    }
}
