<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WolfiSpeechTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.driver', 'array');
        config()->set('cache.default', 'array');
        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_contact_page_exposes_the_voice_disclosure_and_tts_endpoint(): void
    {
        config()->set('wolfi.voices.default', 'google-neural2-d');
        config()->set('services.google_tts.enabled', true);
        config()->set('services.google_tts.api_key', 'google-test-key');

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Voice replies are AI-generated.')
            ->assertSee('assistant\\/speech', false)
            ->assertSee('"tts_available":true', false);
    }

    public function test_assistant_speech_endpoint_returns_google_audio_when_configured(): void
    {
        config()->set('wolfi.voices.default', 'google-neural2-d');
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
            'text' => 'Cuando puedo solicitar mi primer payout?',
            'locale' => 'es-ES',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/mpeg');
        $response->assertHeader('X-Wolfi-TTS-Provider', 'google_cloud');
        $response->assertHeader('X-Wolfi-TTS-Voice', 'google-neural2-d');
        $this->assertSame('fake-google-audio', $response->getContent());

        Http::assertSent(function ($request): bool {
            return Str::startsWith($request->url(), 'https://texttospeech.googleapis.com/v1/text:synthesize?key=')
                && data_get($request->data(), 'voice.languageCode') === 'es-ES'
                && data_get($request->data(), 'voice.name') === 'es-ES-Neural2-B'
                && data_get($request->data(), 'audioConfig.audioEncoding') === 'MP3';
        });
    }

    public function test_assistant_speech_endpoint_returns_service_unavailable_without_provider_configuration(): void
    {
        config()->set('wolfi.voices.default', 'google-neural2-d');
        config()->set('services.google_tts.enabled', true);
        config()->set('services.google_tts.api_key', null);

        Http::fake();

        $this->post(route('assistant.speech'), [
            'text' => 'When can I request my first payout?',
            'locale' => 'en-US',
        ])->assertStatus(503)
            ->assertJson([
                'message' => __('site.contact.voice_audio_unavailable'),
            ]);

        Http::assertNothingSent();
    }
}
