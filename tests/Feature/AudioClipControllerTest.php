<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Jobs\ProcessAudioClip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioClipControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
        Session::start();
    }

    public function test_audio_clip_store_records_log_start_event(): void
    {
        Queue::fake();
        Storage::fake('local');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/audio-clips', [
            '_token' => Session::token(),
            'audio' => UploadedFile::fake()->create('clip.webm', 100, 'audio/webm'),
            'duration_sec' => 10,
            'codec' => 'webm-opus',
            'sample_rate' => 48000,
        ]);

        $response->assertCreated();

        Queue::assertPushed(ProcessAudioClip::class);

        $this->assertDatabaseHas('telemetry_events', [
            'user_id' => $user->id,
            'event_type' => 'log_start',
        ]);
    }
}
