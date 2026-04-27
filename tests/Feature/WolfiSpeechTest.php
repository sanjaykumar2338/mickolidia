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
        config()->set('wolfi.speech_cache.store', 'array');
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

    public function test_assistant_speech_endpoint_returns_elevenlabs_david_audio_by_default(): void
    {
        config()->set('wolfi.voices.default', 'elevenlabs-david');
        config()->set('services.elevenlabs.tts.enabled', true);
        config()->set('services.elevenlabs.api_key', 'elevenlabs-test-key');
        config()->set('services.elevenlabs.voice_id', 'id7LQ3n0ft94moeTT1ER');
        config()->set('services.elevenlabs.base_url', 'https://api.elevenlabs.io');
        config()->set('services.elevenlabs.timeout', 20);
        config()->set('services.elevenlabs.tts.model', 'eleven_multilingual_v2');
        config()->set('services.elevenlabs.tts.output_format', 'mp3_44100_128');

        Http::fake([
            'https://api.elevenlabs.io/v1/text-to-speech/*' => Http::response('fake-elevenlabs-audio', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $response = $this->post(route('assistant.speech'), [
            'text' => 'Hello from Wolfi using ElevenLabs.',
            'locale' => 'en-US',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/mpeg');
        $response->assertHeader('X-Wolfi-TTS-Provider', 'elevenlabs');
        $response->assertHeader('X-Wolfi-TTS-Voice', 'elevenlabs-david');
        $response->assertHeader('X-Wolfi-TTS-Model', 'eleven_multilingual_v2');
        $this->assertSame('fake-elevenlabs-audio', $response->getContent());

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.elevenlabs.io/v1/text-to-speech/id7LQ3n0ft94moeTT1ER?output_format=mp3_44100_128'
                && $request->hasHeader('xi-api-key', 'elevenlabs-test-key')
                && data_get($request->data(), 'model_id') === 'eleven_multilingual_v2'
                && data_get($request->data(), 'text') === 'Hello from Wolfi using ElevenLabs.';
        });
    }

    public function test_assistant_speech_endpoint_caches_matching_elevenlabs_audio_requests(): void
    {
        config()->set('wolfi.voices.default', 'elevenlabs-david');
        config()->set('services.elevenlabs.tts.enabled', true);
        config()->set('services.elevenlabs.api_key', 'elevenlabs-test-key');
        config()->set('services.elevenlabs.voice_id', 'id7LQ3n0ft94moeTT1ER');
        config()->set('services.elevenlabs.base_url', 'https://api.elevenlabs.io');
        config()->set('services.elevenlabs.tts.model', 'eleven_multilingual_v2');
        config()->set('services.elevenlabs.tts.output_format', 'mp3_44100_128');

        Http::fake([
            'https://api.elevenlabs.io/v1/text-to-speech/*' => Http::response('cached-elevenlabs-audio', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $payload = [
            'text' => 'Hello from Wolfi using cached ElevenLabs audio.',
            'locale' => 'en-US',
        ];

        $this->post(route('assistant.speech'), $payload)
            ->assertOk()
            ->assertHeader('X-Wolfi-TTS-Provider', 'elevenlabs');

        $this->post(route('assistant.speech'), $payload)
            ->assertOk()
            ->assertHeader('X-Wolfi-TTS-Provider', 'elevenlabs');

        Http::assertSentCount(1);
    }

    public function test_assistant_speech_retries_elevenlabs_fallback_voice_when_david_requires_paid_plan(): void
    {
        config()->set('wolfi.voices.default', 'elevenlabs-david');
        config()->set('services.elevenlabs.tts.enabled', true);
        config()->set('services.elevenlabs.api_key', 'elevenlabs-test-key');
        config()->set('services.elevenlabs.voice_id', 'id7LQ3n0ft94moeTT1ER');
        config()->set('services.elevenlabs.fallback_voice_id', 'IKne3meq5aSn9XLyUdCD');
        config()->set('services.elevenlabs.base_url', 'https://api.elevenlabs.io');
        config()->set('services.elevenlabs.tts.model', 'eleven_multilingual_v2');
        config()->set('services.elevenlabs.tts.output_format', 'mp3_44100_128');

        Http::fake([
            'https://api.elevenlabs.io/v1/text-to-speech/id7LQ3n0ft94moeTT1ER*' => Http::response([
                'detail' => [
                    'code' => 'paid_plan_required',
                    'message' => 'Free users cannot use library voices via the API.',
                ],
            ], 402),
            'https://api.elevenlabs.io/v1/text-to-speech/IKne3meq5aSn9XLyUdCD*' => Http::response('fake-elevenlabs-fallback-audio', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $response = $this->post(route('assistant.speech'), [
            'text' => 'Hello from Wolfi using ElevenLabs fallback.',
            'locale' => 'en-US',
        ]);

        $response->assertOk();
        $response->assertHeader('X-Wolfi-TTS-Provider', 'elevenlabs');
        $response->assertHeader('X-Wolfi-TTS-Voice', 'elevenlabs-david');
        $this->assertSame('fake-elevenlabs-fallback-audio', $response->getContent());

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.elevenlabs.io/v1/text-to-speech/IKne3meq5aSn9XLyUdCD?output_format=mp3_44100_128');
    }

    public function test_elevenlabs_david_uses_multilingual_model_for_supported_platform_languages(): void
    {
        config()->set('wolfi.voices.default', 'elevenlabs-david');
        config()->set('services.elevenlabs.tts.enabled', true);
        config()->set('services.elevenlabs.api_key', 'elevenlabs-test-key');
        config()->set('services.elevenlabs.voice_id', 'id7LQ3n0ft94moeTT1ER');
        config()->set('services.elevenlabs.base_url', 'https://api.elevenlabs.io');
        config()->set('services.elevenlabs.tts.model', 'eleven_multilingual_v2');

        Http::fake([
            'https://api.elevenlabs.io/v1/text-to-speech/*' => Http::response('fake-elevenlabs-audio', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        foreach (['en-US', 'es-ES', 'de-DE', 'fr-FR'] as $locale) {
            $this->post(route('assistant.speech'), [
                'text' => "Wolfi language check for {$locale}.",
                'locale' => $locale,
            ])->assertOk()
                ->assertHeader('X-Wolfi-TTS-Provider', 'elevenlabs')
                ->assertHeader('X-Wolfi-TTS-Voice', 'elevenlabs-david')
                ->assertHeader('X-Wolfi-TTS-Model', 'eleven_multilingual_v2');
        }

        Http::assertSentCount(4);
    }

    public function test_assistant_speech_endpoint_returns_service_unavailable_without_provider_configuration(): void
    {
        config()->set('wolfi.voices.default', 'elevenlabs-david');
        config()->set('services.elevenlabs.tts.enabled', true);
        config()->set('services.elevenlabs.api_key', null);

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
