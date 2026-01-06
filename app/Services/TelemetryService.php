<?php

namespace App\Services;

use App\Models\TelemetryEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TelemetryService
{
    public function record(?User $user, string $eventType, array $context = []): void
    {
        TelemetryEvent::query()->create([
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'context' => $context,
        ]);

        Log::channel('stack')->info('Telemetry event recorded', [
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'context' => $context,
        ]);
    }
}

