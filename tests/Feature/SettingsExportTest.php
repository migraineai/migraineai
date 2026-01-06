<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Jobs\DeleteUserAccount;
use App\Jobs\GenerateUserDataExport;
use App\Models\AudioClip;
use App\Models\Episode;
use App\Models\User;
use App\Models\UserDataExport;
use App\Models\UserDeletionRequest;
use App\Services\DataExportService;
use App\Services\TelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SettingsExportTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $csrfHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
        Session::start();
        $this->csrfHeaders = ['X-CSRF-TOKEN' => csrf_token()];
    }

    public function test_user_can_request_data_export(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/request-export', [], $this->csrfHeaders);

        $response->assertStatus(202)->assertJsonPath('export.status', UserDataExport::STATUS_PENDING);

        $this->assertDatabaseHas('user_data_exports', [
            'user_id' => $user->id,
            'status' => UserDataExport::STATUS_PENDING,
        ]);

        Queue::assertPushed(GenerateUserDataExport::class);
        $this->assertCount(1, Queue::pushed(GenerateUserDataExport::class));
    }

    public function test_export_listing_endpoint_returns_recent_exports(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $pending = UserDataExport::factory()->pending()->for($user)->create();
        $ready = UserDataExport::factory()->for($user)->create();
        UserDataExport::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->getJson('/settings/exports');

        $response->assertOk();
        $exports = collect($response->json('exports'));

        $this->assertCount(2, $exports);
        $this->assertTrue($exports->contains(fn ($export) => $export['id'] === $ready->id));
        $this->assertIsString($exports->firstWhere('id', $ready->id)['download_url']);
        $this->assertTrue($exports->contains(fn ($export) => $export['id'] === $pending->id));
        $this->assertNull($exports->firstWhere('id', $pending->id)['download_url']);
    }

    public function test_request_deletion_schedules_job_when_queue_is_async(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/request-deletion', [], $this->csrfHeaders);

        $response->assertStatus(202)->assertJsonPath('deletion_request.status', UserDeletionRequest::STATUS_SCHEDULED);

        $record = UserDeletionRequest::first();
        $this->assertNotNull($record);
        $this->assertTrue($record->scheduled_for->greaterThan(Carbon::now()->addHours(71)));

        Queue::assertPushed(DeleteUserAccount::class);
        $this->assertCount(1, Queue::pushed(DeleteUserAccount::class));
    }

    public function test_request_deletion_does_not_dispatch_when_queue_is_sync(): void
    {
        config(['queue.default' => 'sync']);
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/settings/request-deletion', [], $this->csrfHeaders)->assertStatus(202);

        Queue::assertNothingPushed();
    }

    public function test_generate_user_data_export_job_creates_archive(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'cycle_tracking_enabled' => true,
            'cycle_length_days' => 27,
            'period_length_days' => 4,
            'last_period_start_date' => Carbon::now()->subDays(10),
        ]);

        $clip = AudioClip::factory()->for($user)->create([
            'structured_payload' => ['intensity' => 6],
        ]);

        Episode::factory()->for($user)->create([
            'audio_clip_id' => $clip->id,
            'start_time' => Carbon::now()->subDays(2),
            'end_time' => Carbon::now()->subDays(2)->addHour(),
        ]);

        $export = UserDataExport::factory()->pending()->for($user)->create([
            'disk' => 'local',
        ]);

        $job = new GenerateUserDataExport($export->id);
        $job->handle(app(DataExportService::class), app(TelemetryService::class));

        $export->refresh();

        $this->assertSame(UserDataExport::STATUS_READY, $export->status);
        $this->assertNotNull($export->download_token);
        $this->assertNotNull($export->path);
        $this->assertNotNull($export->size_bytes);

        Storage::disk('local')->assertExists($export->path);

        $zip = new ZipArchive();
        $zip->open(Storage::disk('local')->path($export->path));

        $this->assertGreaterThan(0, $zip->numFiles);
        $this->assertGreaterThanOrEqual(0, $zip->locateName('profile.json'));
        $zip->close();

        $this->assertDatabaseHas('telemetry_events', [
            'user_id' => $user->id,
            'event_type' => 'pdf_export',
        ]);
    }

    public function test_delete_user_account_job_removes_user_and_audio_assets(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $clip = AudioClip::factory()->for($user)->create([
            'storage_path' => 'audio-clips/' . $user->id . '/clip.webm',
        ]);

        Storage::disk('local')->put($clip->storage_path, 'audio-bytes');

        $request = UserDeletionRequest::factory()->for($user)->scheduled()->create();

        $job = new DeleteUserAccount($request->id);
        $job->handle();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('audio_clips', ['id' => $clip->id]);

        Storage::disk('local')->assertMissing($clip->storage_path);

        $this->assertDatabaseHas('user_deletion_requests', [
            'id' => $request->id,
            'status' => UserDeletionRequest::STATUS_COMPLETED,
        ]);
    }
}
