<?php

namespace Tests\Feature;

use App\Http\Controllers\VoiceAssistantController;
use App\Models\User;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class VoiceAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function mockOpenAIService(array $analysis, array $assistant): void
    {
        $mock = $this->getMockBuilder(OpenAIService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['extractEpisodeData', 'generateVoiceAssistantResponse'])
            ->getMock();

        $mock->method('extractEpisodeData')->willReturn($analysis);
        $mock->method('generateVoiceAssistantResponse')->willReturn($assistant);

        $this->app->instance(OpenAIService::class, $mock);
    }

    public function test_voice_analyze_prioritizes_followups_and_maps_fields(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $rawAnalysis = [
            'start_time' => now()->subDay()->setTime(21, 0)->utc()->toIso8601String(),
            'triggers' => ['stress'],
            'confidence_breakdown' => [
                'start_time' => 0.92,
                'triggers' => 0.8,
            ],
        ];

        $assistantJson = [
            'assistant_response' => 'On a scale of 1 to 10, how intense is the pain?',
            'is_followup_required' => true,
            'next_question_field' => 'intensity',
        ];

        $this->mockOpenAIService($rawAnalysis, $assistantJson);

        $payload = [
            'transcript' => 'I had a migraine last night at 9pm, triggered by stress.',
            'answered_fields' => [],
            'asked_fields' => [],
        ];

        $response = $this->postJson(route('voice.analyze'), $payload);

        $response->assertOk();

        $data = $response->json();

        $this->assertArrayHasKey('structured_payload', $data);
        $this->assertArrayHasKey('missing_fields', $data);
        $this->assertArrayHasKey('assistant_response', $data);
        $this->assertArrayHasKey('next_question_field', $data);

        $this->assertIsArray($data['structured_payload']);

        $missing = $data['missing_fields'];
        $this->assertContains('intensity', $missing);
        $this->assertContains('pain_location', $missing);
        $this->assertContains('symptoms', $missing);
        $this->assertTrue(in_array('intensity', $missing, true));
        $this->assertTrue($data['is_followup_required']);
    }
}
