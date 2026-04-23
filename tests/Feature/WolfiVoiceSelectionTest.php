<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminBasicAuth;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\Wolfi\WolfiVoiceSettings;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
            ->withSession($this->adminSessionState())
            ->get(route('dashboard.wolfi.voices'))
            ->assertOk()
            ->assertSee('Select Wolfi Voice')
            ->assertSee('Web Speech Guide')
            ->assertSee('Play Preview')
            ->assertSee('Save Selected Voice');
    }

    public function test_selected_voice_is_saved_in_platform_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession($this->adminSessionState())
            ->post(route('dashboard.wolfi.voices.update'), [
                'voice_id' => 'google-neural2-d',
            ])
            ->assertRedirect(route('dashboard.wolfi.voices'));

        $setting = AppSetting::query()->where('key', WolfiVoiceSettings::SELECTED_VOICE_SETTING_KEY)->first();

        $this->assertNotNull($setting);
        $this->assertSame('google-neural2-d', data_get($setting?->value, 'voice_id'));
    }

    public function test_assistant_speech_uses_saved_platform_voice_by_default_when_google_cloud_is_configured(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => WolfiVoiceSettings::SELECTED_VOICE_SETTING_KEY],
            ['value' => ['voice_id' => 'google-neural2-d']],
        );

        config()->set('services.google_tts.enabled', true);
        config()->set('services.google_tts.api_key', 'google-test-key');
        config()->set('services.google_tts.base_url', 'https://texttospeech.googleapis.com');
        config()->set('services.google_tts.timeout', 20);
        config()->set('services.google_tts.audio_encoding', 'MP3');

        Http::fake([
            'https://texttospeech.googleapis.com/v1/text:synthesize*' => Http::response([
                'audioContent' => base64_encode('fake-google-audio'),
            ], 200),
        ]);

        $response = $this->post(route('assistant.speech'), [
            'text' => 'Hello from Wolfi voice test.',
            'locale' => 'en-US',
        ]);

        $response->assertOk();
        $response->assertHeader('X-Wolfi-TTS-Provider', 'google_cloud');
        $response->assertHeader('X-Wolfi-TTS-Voice', 'google-neural2-d');
        $response->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertSame('fake-google-audio', $response->getContent());

        Http::assertSent(function ($request): bool {
            return Str::startsWith($request->url(), 'https://texttospeech.googleapis.com/v1/text:synthesize?key=')
                && data_get($request->data(), 'voice.name') === 'en-US-Neural2-D'
                && data_get($request->data(), 'audioConfig.audioEncoding') === 'MP3';
        });
    }

    public function test_dashboard_voice_preview_endpoint_uses_requested_voice_for_elevenlabs(): void
    {
        $user = User::factory()->create();

        config()->set('services.elevenlabs.tts.enabled', true);
        config()->set('services.elevenlabs.api_key', 'elevenlabs-test-key');
        config()->set('services.elevenlabs.base_url', 'https://api.elevenlabs.io');
        config()->set('services.elevenlabs.timeout', 20);
        config()->set('services.elevenlabs.tts.model', 'eleven_multilingual_v2');
        config()->set('services.elevenlabs.tts.output_format', 'mp3_44100_128');

        Http::fake([
            'https://api.elevenlabs.io/v1/text-to-speech/*' => Http::response('fake-preview-audio', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $response = $this->actingAs($user)
            ->withSession($this->adminSessionState())
            ->post(route('dashboard.wolfi.voices.preview'), [
                'voice_id' => 'elevenlabs-adam',
                'text' => 'Preview voice sample.',
                'locale' => 'en-US',
            ]);

        $response->assertOk();
        $response->assertHeader('X-Wolfi-TTS-Provider', 'elevenlabs');
        $response->assertHeader('X-Wolfi-TTS-Voice', 'elevenlabs-adam');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.elevenlabs.io/v1/text-to-speech/pNInz6obpgDQGcFmaJgB'
                && data_get($request->data(), 'model_id') === 'eleven_multilingual_v2'
                && data_get($request->data(), 'text') === 'Preview voice sample.';
        });
    }

    public function test_voice_routes_are_blocked_for_non_admin_dashboard_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.wolfi.voices'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('dashboard.wolfi.voices.update'), [
                'voice_id' => 'google-neural2-d',
            ])
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('dashboard.wolfi.voices.preview'), [
                'voice_id' => 'google-neural2-d',
            ])
            ->assertRedirect(route('admin.login'));
    }

    public function test_voice_sidebar_link_is_visible_only_for_admin_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Wolfi Voices');

        $this->actingAs($user)
            ->withSession($this->adminSessionState())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Wolfi Voices');
    }

    public function test_admin_menu_exposes_wolfi_voices_page_without_user_authentication(): void
    {
        $this->withSession($this->adminSessionState())
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Wolfi Voices');

        $this->withSession($this->adminSessionState())
            ->get(route('admin.wolfi.voices'))
            ->assertOk()
            ->assertSee('Select Wolfi Voice')
            ->assertSee('name="csrf-token"', false)
            ->assertSee(route('admin.wolfi.voices.update', [], false), false)
            ->assertSee(route('admin.wolfi.voices.preview', [], false), false);
    }

    public function test_admin_voice_update_redirects_back_to_admin_voice_page(): void
    {
        $this->withSession($this->adminSessionState())
            ->post(route('admin.wolfi.voices.update'), [
                'voice_id' => 'google-neural2-d',
            ])
            ->assertRedirect(route('admin.wolfi.voices'));
    }

    /**
     * @return array<string, mixed>
     */
    private function adminSessionState(): array
    {
        return [
            AdminBasicAuth::SESSION_KEY => true,
            AdminBasicAuth::USERNAME_KEY => (string) config('wolforix.admin_auth.username', 'admin'),
        ];
    }
}
