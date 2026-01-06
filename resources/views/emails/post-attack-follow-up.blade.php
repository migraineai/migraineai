<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            background: #f5f7fb;
            color: #111827;
            font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
        }
        .container {
            max-width: 560px;
            margin: 0 auto;
            background: #fff;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 16px 45px rgba(15, 23, 42, 0.08);
        }
        h1 {
            margin-top: 0;
            color: #0f766e;
        }
        p {
            line-height: 1.6;
            font-size: 15px;
        }
        .card {
            background: #eefcf8;
            border-radius: 12px;
            padding: 16px 20px;
            margin: 20px 0;
        }
        .card-title {
            margin: 0 0 8px 0;
            font-weight: 600;
            color: #065f46;
        }
        .card-list {
            margin: 0;
            padding-left: 18px;
            color: #064e3b;
            font-size: 14px;
        }
        .cta {
            display: inline-block;
            margin: 24px 0;
            background: #0f766e;
            color: #fff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 999px;
            font-weight: 600;
        }
        .footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: #6b7280;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>We’re checking in</h1>
    <p>Hi {{ $firstName }},</p>
    <p>
        This is your gentle reminder to take a breath and note how you’re recovering after your recent migraine.
        Logging how you feel a few hours post-attack can highlight what care routines or medications are making a difference.
    </p>

    @php
        $loggedAt = null;
        if ($episode?->start_time) {
            $loggedAt = $episode->start_time->copy()->setTimezone($timeZone)->format('M j, Y g:i A');
        }
        $intensity = $episode?->intensity;
        $triggers = $episode && is_array($episode->triggers) && count($episode->triggers)
            ? implode(', ', $episode->triggers)
            : null;
        $whatHelped = $episode?->what_you_tried ?? null;
        $notes = $episode?->notes ?? null;
    @endphp

    @if($episode)
        <div class="card">
            <p class="card-title">Episode snapshot</p>
            <ul class="card-list">
                @if($loggedAt)
                    <li>Logged: {{ $loggedAt }} ({{ $timeZone }})</li>
                @endif
                @if(!is_null($intensity))
                    <li>Intensity: {{ $intensity }}/10</li>
                @endif
                @if($triggers)
                    <li>Possible triggers: {{ $triggers }}</li>
                @endif
                @if($whatHelped)
                    <li>What helped: {{ $whatHelped }}</li>
                @endif
                @if($notes)
                    <li>Notes: {{ $notes }}</li>
                @endif
            </ul>
        </div>
    @endif

    <p>
        When you’re ready, spend a minute updating how you feel now—hydration, rest, meds, or anything else that’s helping.
        These follow-ups give MigraineAI the context needed to reflect patterns back to you and your clinician.
    </p>

    <a href="{{ $ctaUrl }}" class="cta">Update your recovery log</a>

    <p>
        Wishing you steady ease,<br>
        MigraineAI Team
    </p>

    <div class="footer">
        You’re receiving this because you enabled post-attack follow-ups in your reminder settings.
    </div>
</div>
</body>
</html>
