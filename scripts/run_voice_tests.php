<?php
declare(strict_types=1);

use Carbon\Carbon;
use App\Support\StructuredEpisodeMapper;

require __DIR__ . '/../vendor/autoload.php';

function loadTestCases(string $path): array {
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $tests = [];
    $current = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') { continue; }

        if (preg_match('/^(\d{1,3})\\.\s*(.+)$/', $line, $m)) {
            if ($current) { $tests[] = $current; }
            $current = [
                'id' => (int)$m[1],
                'sentence' => trim($m[2]),
                'expected' => [
                    'start_time' => null,
                    'triggers' => [],
                    'intensity' => null,
                    'pain_location' => null,
                    'symptoms' => [],
                    'aura' => null,
                ],
            ];
            continue;
        }

        if ($current && str_starts_with($line, '=>')) {
            // Parse key : value
            $kv = trim(substr($line, 2));
            $parts = preg_split('/\s*:\s*/', $kv, 2);
            if (!$parts || count($parts) < 2) { continue; }
            [$key, $value] = $parts;
            $key = strtolower(trim($key));
            $value = trim($value);

            // Normalize key typos
            if ($key === 'trigger' || $key === 'triggers') { $key = 'triggers'; }
            if ($key === 'symptom' || $key === 'symptoms') { $key = 'symptoms'; }
            if ($key === 'paint_location' || $key === 'pain_location') { $key = 'pain_location'; }

            switch ($key) {
                case 'intensity':
                    $current['expected']['intensity'] = is_numeric($value) ? (int)$value : null;
                    break;
                case 'aura':
                    $current['expected']['aura'] = preg_match('/1|true|yes/i', $value) ? 1 : 0;
                    break;
                case 'pain_location':
                    $current['expected']['pain_location'] = $value !== '' ? strtolower($value) : null;
                    break;
                case 'symptoms':
                    $tokens = array_values(array_filter(array_map(fn($t) => trim($t), preg_split('/[,\s]+/', $value))));
                    $current['expected']['symptoms'] = $tokens;
                    break;
                case 'triggers':
                    $tokens = array_values(array_filter(array_map(fn($t) => trim($t), preg_split('/[,\s]+/', $value))));
                    $current['expected']['triggers'] = $tokens;
                    break;
                case 'start_time':
                    $current['expected']['start_time'] = $value;
                    break;
            }
        }
    }

    if ($current) { $tests[] = $current; }
    return $tests;
}

function computeExpectedStartIso(null|string $raw): ?string {
    if (!$raw) { return null; }
    // Extract inside brackets if present
    if (preg_match('/\[(.+)\]/', $raw, $m)) { $raw = $m[1]; }
    $tz = 'Asia/Kolkata';
    $now = Carbon::now($tz);
    $text = strtolower(trim($raw));
    if ($text === '' || $text === 'null') { return null; }

    // Now, CurrentTime, Timestamp_Now
    if (str_contains($text, 'timestamp_now') || $text === 'currenttime' || str_contains($text, 'currenttime')) {
        return $now->copy()->toIso8601String();
    }

    // Relative: -X Hours / Mins Ago
    if (preg_match('/(-?\d+)\s*(hour|hours|hr|hrs|minute|minutes|min|mins)/', $text, $m)) {
        $n = (int)$m[1];
        $unit = $m[2];
        $dt = $now->copy();
        if (str_contains($unit, 'hour') || str_contains($unit, 'hr')) { $dt = $dt->subHours(abs($n)); }
        else { $dt = $dt->subMinutes(abs($n)); }
        return $dt->toIso8601String();
    }

    // Today HH:MM AM/PM
    if (str_starts_with($text, 'today')) {
        $t = trim(str_replace('today', '', $text));
        $base = $now->copy()->startOfDay()->setTime(9,0,0);
        if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(am|pm)/i', $t, $m)) {
            $h = (int)$m[1]; $min = isset($m[2]) ? (int)$m[2] : 0; $mer = strtolower($m[3]);
            if ($mer === 'am' && $h === 12) { $h = 0; }
            elseif ($mer === 'pm' && $h < 12) { $h += 12; }
            return $base->copy()->setTime($h, $min)->toIso8601String();
        }
        return $base->toIso8601String();
    }

    // Yesterday ...
    if (str_starts_with($text, 'yesterday')) {
        $t = trim(str_replace('yesterday', '', $text));
        $base = $now->copy()->subDay()->startOfDay()->setTime(21,0,0); // 9pm default
        if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(am|pm)/i', $t, $m)) {
            $h = (int)$m[1]; $min = isset($m[2]) ? (int)$m[2] : 0; $mer = strtolower($m[3]);
            if ($mer === 'am' && $h === 12) { $h = 0; }
            elseif ($mer === 'pm' && $h < 12) { $h += 12; }
            return $base->copy()->setTime($h, $min)->toIso8601String();
        }
        // morning/afternoon/evening
        if (str_contains($t, 'morning')) { return $now->copy()->subDay()->startOfDay()->setTime(9,0)->toIso8601String(); }
        if (str_contains($t, 'afternoon')) { return $now->copy()->subDay()->startOfDay()->setTime(14,0)->toIso8601String(); }
        if (str_contains($t, 'evening') || str_contains($t, 'night')) { return $now->copy()->subDay()->startOfDay()->setTime(21,0)->toIso8601String(); }
        return $base->toIso8601String();
    }

    // Specific times like 12:00 PM
    if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(am|pm)/i', $text, $m)) {
        $base = $now->copy()->startOfDay();
        $h = (int)$m[1]; $min = isset($m[2]) ? (int)$m[2] : 0; $mer = strtolower($m[3]);
        if ($mer === 'am' && $h === 12) { $h = 0; }
        elseif ($mer === 'pm' && $h < 12) { $h += 12; }
        return $base->copy()->setTime($h, $min)->toIso8601String();
    }

    return null;
}

function normalizeActual(array $actual): array {
    // Ensure keys and types
    return [
        'start_time' => $actual['start_time'] ?? null,
        'triggers' => $actual['triggers'] ?? [],
        'intensity' => $actual['intensity'] ?? null,
        'pain_location' => $actual['pain_location'] ?? null,
        'symptoms' => $actual['symptoms'] ?? [],
        'aura' => isset($actual['aura']) ? ($actual['aura'] ? 1 : 0) : null,
    ];
}

function compareField(string $field, mixed $expected, mixed $actual): bool {
    if ($field === 'start_time') {
        if ($expected === null) { return $actual === null; }
        if (!is_string($actual)) { return false; }
        $expIso = computeExpectedStartIso((string)$expected);
        if ($expIso === null) { return $actual === null; }
        try {
            $exp = Carbon::parse($expIso);
            $act = Carbon::parse($actual);
            $diff = abs($exp->diffInMinutes($act));
            // Heuristic: tags allow ±60m, relative allow ±5m
            $expText = strtolower((string)$expected);
            $tol = (str_contains($expText, 'hour') || str_contains($expText, 'min')) ? 5 : 60;
            return $diff <= $tol;
        } catch (\Throwable) { return false; }
    }

    if ($field === 'intensity') {
        if ($expected === null) { return $actual === null; }
        return (int)$expected === (int)$actual;
    }

    if ($field === 'pain_location') {
        if ($expected === null) { return $actual === null; }
        return strtolower((string)$expected) === strtolower((string)$actual);
    }

    if ($field === 'aura') {
        if ($expected === null) { return $actual === null; }
        return (int)$expected === (int)$actual;
    }

    if ($field === 'triggers' || $field === 'symptoms') {
        $exp = array_map(fn($t) => (string)$t, (array)$expected);
        $act = array_map(fn($t) => (string)$t, (array)$actual);
        foreach ($exp as $token) {
            if ($token === '') { continue; }
            if (!in_array($token, $act, true)) { return false; }
        }
        return true; // allow extra actuals
    }

    return false;
}

function runSuite(string $mode = 'before'): array {
    $path = __DIR__ . '/../Test-Cases-Voice.md';
    $tests = loadTestCases($path);
    $report = [];
    $perFieldTotals = [
        'start_time' => ['pass' => 0, 'total' => 0],
        'intensity' => ['pass' => 0, 'total' => 0],
        'pain_location' => ['pass' => 0, 'total' => 0],
        'triggers' => ['pass' => 0, 'total' => 0],
        'symptoms' => ['pass' => 0, 'total' => 0],
        'aura' => ['pass' => 0, 'total' => 0],
    ];

    foreach ($tests as $t) {
        $start = microtime(true);
        $analysis = []; // Simulated: no model call in test harness
        $actual = StructuredEpisodeMapper::map($analysis, $t['sentence']);
        $norm = normalizeActual($actual);
        $elapsed = (int)round((microtime(true) - $start) * 1000);

        $fields = ['start_time','intensity','pain_location','triggers','symptoms','aura'];
        $fieldResults = [];
        $overallPass = true;
        foreach ($fields as $f) {
            $perFieldTotals[$f]['total']++;
            $ok = compareField($f, $t['expected'][$f], $norm[$f]);
            if ($ok) { $perFieldTotals[$f]['pass']++; }
            $fieldResults[$f] = $ok ? 'PASS' : 'FAIL';
            $overallPass = $overallPass && $ok;
        }

        $report[] = [
            'id' => $t['id'],
            'sentence' => $t['sentence'],
            'expected' => $t['expected'],
            'actual' => $norm,
            'per_field' => $fieldResults,
            'overall' => $overallPass ? 'PASS' : 'FAIL',
            'time_to_parse_ms' => $elapsed,
            'model_calls' => 0,
            'raw_model_replies' => null,
            'session_time_ms' => $elapsed,
            'simulated' => true,
        ];
    }

    // Write outputs
    $dir = dirname(__DIR__);
    $reportFile = $dir . '/report_' . ($mode === 'after' ? 'after' : 'before') . '_changes.json';
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

    $passes = array_reduce($report, fn($acc, $r) => $acc + ($r['overall'] === 'PASS' ? 1 : 0), 0);
    $summary = [];
    $summary[] = 'tests run: ' . count($report);
    $summary[] = 'passes: ' . $passes;
    $summary[] = 'fails: ' . (count($report) - $passes);
    $summary[] = 'per-field accuracy:';
    foreach ($perFieldTotals as $k => $v) {
        $pct = $v['total'] > 0 ? round(($v['pass'] / $v['total']) * 100, 2) : 0;
        $summary[] = sprintf('- %s: %0.2f%%', $k, $pct);
    }

    $times = array_map(fn($r) => $r['time_to_parse_ms'], $report);
    sort($times);
    $avg = array_sum($times) / max(1, count($times));
    $median = $times[(int)floor(count($times)/2)] ?? 0;
    $p95 = $times[(int)floor(count($times)*0.95)] ?? 0;
    $summary[] = 'avg time_to_parse_ms: ' . round($avg, 2);
    $summary[] = 'median time_to_parse_ms: ' . $median;
    $summary[] = 'p95 time_to_parse_ms: ' . $p95;
    $summary[] = 'avg model_calls: 0';

    $slow = array_filter($report, fn($r) => $r['session_time_ms'] > 60000 || $r['time_to_parse_ms'] > 3000);
    if ($slow) {
        $summary[] = 'slow tests:';
        foreach ($slow as $r) { $summary[] = '- id ' . $r['id']; }
    }

    $summaryFile = $dir . '/summary_' . ($mode === 'after' ? 'after' : 'before') . '.txt';
    file_put_contents($summaryFile, implode("\n", $summary));

    // Failures CSV
    $failures = array_filter($report, fn($r) => $r['overall'] !== 'PASS');
    $csv = "id,sentence,reason\n";
    foreach ($failures as $r) {
        $why = [];
        foreach ($r['per_field'] as $f => $res) { if ($res === 'FAIL') { $why[] = $f; } }
        $csv .= sprintf("%d,%s,%s\n", $r['id'], str_replace([",", "\n"], [';', ' '], $r['sentence']), implode('|', $why));
    }
    $csvFile = $dir . '/failures_' . ($mode === 'after' ? 'after' : 'before') . '.csv';
    file_put_contents($csvFile, $csv);

    // Unit tests canonical expected outputs
    $unitTests = array_map(function ($t) {
        return [
            'id' => $t['id'],
            'sentence' => $t['sentence'],
            'expected' => $t['expected'],
        ];
    }, $tests);
    file_put_contents($dir . '/unit_tests.json', json_encode($unitTests, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

    return $report;
}

$mode = 'before';
$round = null;
foreach ($argv as $arg) {
    if ($arg === '--after') { $mode = 'after'; }
    if ($arg === '--before') { $mode = 'before'; }
    if (preg_match('/^--round=(\d+)$/', $arg, $m)) { $round = (int)$m[1]; }
}

$report = runSuite($mode);

// Generate additional round artifacts
if ($mode === 'after') {
    $dir = dirname(__DIR__);
    $suffix = $round ? ('_round_' . $round) : '_round_1';
    // Copy report and summary to round-specific names
    @copy($dir . '/report_after_changes.json', $dir . '/report_after_fix' . $suffix . '.json');
    @copy($dir . '/summary_after.txt', $dir . '/summary_after_fix' . $suffix . '.txt');

    // Top 10 failing tests
    $fails = array_values(array_filter($report, fn($r) => $r['overall'] !== 'PASS'));
    $top10 = array_slice($fails, 0, 10);
    $lines = [];
    foreach ($top10 as $r) {
        $lines[] = 'id: ' . $r['id'];
        $lines[] = 'sentence: ' . $r['sentence'];
        $lines[] = 'expected: ' . json_encode($r['expected']);
        $lines[] = 'actual: ' . json_encode($r['actual']);
        $lines[] = 'time_to_parse_ms: ' . $r['time_to_parse_ms'];
        $lines[] = '---';
    }
    file_put_contents($dir . '/top10_failing_tests.txt', implode("\n", $lines));

    // Root-cause analysis (simple classifier)
    $causeCounts = [
        'missing_synonym_mapping' => 0,
        'numeric_parsing_missed' => 0,
        'adjective_mapping_insufficient' => 0,
        'start_time_hours_ago_unhandled' => 0,
        'woke_up_unhandled' => 0,
    ];
    $csv = "id,cause\n";
    foreach ($fails as $r) {
        $s = strtolower($r['sentence']);
        $cause = null;
        if ($r['per_field']['start_time'] === 'FAIL') {
            if (preg_match('/\bwoke up\b/', $s)) { $cause = 'woke_up_unhandled'; }
            elseif (preg_match('/\b(\d+)\s*(hour|hours|hr|hrs|min|mins)\s*ago\b/', $s)) { $cause = 'start_time_hours_ago_unhandled'; }
        }
        if (!$cause && $r['per_field']['intensity'] === 'FAIL') {
            if (preg_match('/\b(\d)\s*out of\s*10\b/', $s)) { $cause = 'numeric_parsing_missed'; }
            elseif (preg_match('/\b(mild|moderate|severe|throbbing|splitting|stabbing|shooting|heavy|crushing|unbearable|worst|violent|killer|killing|exploding|excruciating|dull)\b/', $s)) { $cause = 'adjective_mapping_insufficient'; }
        }
        if (!$cause && $r['per_field']['pain_location'] === 'FAIL') {
            if (preg_match('/temple|eyebrow|jaw|teeth|cheek|sinus|behind my .* eye|eye socket|socket/', $s)) { $cause = 'missing_synonym_mapping'; }
        }
        if ($cause) {
            $causeCounts[$cause]++;
            $csv .= $r['id'] . ',' . $cause . "\n";
        }
    }
    file_put_contents($dir . '/analysis_root_causes.csv', $csv);

    // Added unit tests targeting new synonyms
    $added = [
        ['sentence' => 'It is an excruciating splitting pain in my temples.', 'expected_intensity' => 9, 'expected_location' => 'frontal'],
        ['sentence' => 'I woke up with a throbbing pain on my left side.', 'expected_intensity' => 6, 'expected_start_tag' => 'Today 08:00 AM'],
        ['sentence' => 'The pain started 2 hours ago after I skipped a meal.', 'expected_start_relative' => '-2h', 'expected_trigger' => 'Hunger'],
        ['sentence' => 'I have a mild headache behind my right eye.', 'expected_intensity' => 2, 'expected_location' => 'frontal'],
        ['sentence' => 'Just a dull ache today, maybe a 3 out of 10.', 'expected_intensity' => 3, 'expected_start_tag' => 'Today 08:00 AM'],
        ['sentence' => 'Intermittent shooting pain in the ear.', 'expected_intensity' => 8, 'expected_location' => 'other'],
        ['sentence' => 'It feels violent and killing me right now.', 'expected_intensity' => 9],
        ['sentence' => 'A killer headache started at dawn.', 'expected_intensity' => 9, 'expected_start_tag' => 'Today 05:00 AM'],
    ];
    file_put_contents($dir . '/unit_tests_added.json', json_encode($added, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}
