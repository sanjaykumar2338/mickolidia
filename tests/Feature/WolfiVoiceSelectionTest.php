<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\Wolfi\WolfiVoiceSettings;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WolfiVoiceSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.driver', 'array');
        config()->set('cache.default', 'array');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_dashboard_voice_page_displays_options_and_current_selection(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.wolfi.voices'))
            ->assertOk()
            ->assertSee('Select Wolfi Voice')
            ->assertSee('Onyx')
            ->assertSee('Play Preview')
            ->assertSee('Save Selected Voice');
    }

    public function test_selected_voice_is_saved_in_platform_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('dashboard.wolfi.voices.update'), [
                'voice_id' => 'nova',
            ])
            ->assertRedirect(route('dashboard.wolfi.voices'));

        $setting = AppSetting::query()->where('key', WolfiVoiceSettings::SELECTED_VOICE_SETTING_KEY)->first();

        $this->assertNotNull($setting);
        $this->assertSame('nova', data_get($setting?->value, 'voice_id'));
    }

    public function test_assistant_speech_uses_saved_platform_voice_by_default(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => WolfiVoiceSettings::SELECTED_VOICE_SETTING_KEY],
            ['value' => ['voice_id' => 'nova']],
        );

        config()->set('services.openai.api_key', 'test-openai-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.timeout', 20);
        config()->set('services.openai.tts.enabled', true);
        config()->set('services.openai.tts.model', 'gpt-4o-mini-tts');
        config()->set('services.openai.tts.voice', 'onyx');
        config()->set('services.openai.tts.format', 'mp3');
        config()->set('services.openai.tts.speed', 0.94);

        Http::fake([
            'https://api.openai.com/v1/audio/speech' => Http::response('fake-mp3-audio', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $response = $this->post(route('assistant.speech'), [
            'text' => 'Hello from Wolfi voice test.',
            'locale' => 'en-US',
        ]);

        $response->assertOk();
        $response->assertHeader('X-Wolfi-TTS-Voice', 'nova');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/audio/speech'
                && $request['voice'] === 'nova';
        });
    }

    public function test_dashboard_voice_preview_endpoint_uses_requested_voice(): void
    {
        $user = User::factory()->create();

        config()->set('services.openai.api_key', 'test-openai-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.timeout', 20);
        config()->set('services.openai.tts.enabled', true);
        config()->set('services.openai.tts.model', 'gpt-4o-mini-tts');
        config()->set('services.openai.tts.voice', 'onyx');
        config()->set('services.openai.tts.format', 'mp3');
        config()->set('services.openai.tts.speed', 0.94);

        Http::fake([
            'https://api.openai.com/v1/audio/speech' => Http::response('fake-preview-audio', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('dashboard.wolfi.voices.preview'), [
            'voice_id' => 'sage',
            'text' => 'Preview voice sample.',
            'locale' => 'en-US',
        ]);

        $response->assertOk();
        $response->assertHeader('X-Wolfi-TTS-Voice', 'sage');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/audio/speech'
                && $request['voice'] === 'sage'
                && $request['input'] === 'Preview voice sample.';
        });
    }
}
