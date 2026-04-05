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
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_contact_page_exposes_the_voice_disclosure_and_tts_endpoint(): void
    {
        config()->set('services.openai.api_key', 'test-openai-key');
        config()->set('services.openai.tts.enabled', true);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Voice replies are AI-generated.')
            ->assertSee('assistant\\/speech', false)
            ->assertSee('"tts_available":true', false);
    }

    public function test_assistant_speech_endpoint_returns_openai_audio_when_configured(): void
    {
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
            'text' => 'Cuando puedo solicitar mi primer payout?',
            'locale' => 'es-ES',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/mpeg');
        $response->assertHeader('X-Wolfi-TTS-Provider', 'openai');
        $response->assertHeader('X-Wolfi-TTS-Voice', 'onyx');
        $this->assertSame('fake-mp3-audio', $response->getContent());

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/audio/speech'
                && $request['model'] === 'gpt-4o-mini-tts'
                && $request['voice'] === 'onyx'
                && $request['response_format'] === 'mp3'
                && (float) $request['speed'] === 0.94
                && Str::contains((string) $request['instructions'], 'young male voice')
                && Str::contains((string) $request['instructions'], 'Speak entirely in Spanish');
        });
    }

    public function test_assistant_speech_endpoint_returns_service_unavailable_without_openai_configuration(): void
    {
        config()->set('services.openai.api_key', null);
        config()->set('services.openai.tts.enabled', true);

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
