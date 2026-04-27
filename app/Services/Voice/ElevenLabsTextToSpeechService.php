<?php

namespace App\Services\Voice;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ElevenLabsTextToSpeechService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.elevenlabs.tts.enabled', true)
            && filled(config('services.elevenlabs.api_key'));
    }

    /**
     * @return array{audio: string, content_type: string, locale: string, voice: string, model: string, provider: string}
     */
    public function synthesize(string $text, ?string $locale = null, ?string $voiceId = null, ?string $wolfiVoiceId = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('ElevenLabs TTS is not configured.');
        }

        $providerVoiceId = trim((string) $voiceId);

        if ($providerVoiceId === '') {
            $providerVoiceId = (string) config('services.elevenlabs.voice_id', '');
        }

        if ($providerVoiceId === '') {
            throw new RuntimeException('ElevenLabs voice ID is missing.');
        }

        $localeBase = $this->normalizeLocale($locale);
        $input = trim(Str::limit($text, 2000, ''));
        $model = (string) config('services.elevenlabs.tts.model', 'eleven_multilingual_v2');
        $outputFormat = (string) config('services.elevenlabs.tts.output_format', 'mp3_44100_128');
        $usedProviderVoiceId = $providerVoiceId;

        try {
            $response = $this->sendRequest($providerVoiceId, $input, $model, $outputFormat);

            if ($this->shouldRetryWithFallbackVoice($response, $providerVoiceId)) {
                $fallbackVoiceId = (string) config('services.elevenlabs.fallback_voice_id');

                Log::warning('ElevenLabs selected voice requires a paid plan; retrying configured ElevenLabs fallback voice.', [
                    'status' => $response->status(),
                    'voice_id' => $wolfiVoiceId ?: $providerVoiceId,
                    'model' => $model,
                ]);

                $usedProviderVoiceId = $fallbackVoiceId;
                $response = $this->sendRequest($fallbackVoiceId, $input, $model, $outputFormat);
            }

            $response->throw();
        } catch (RequestException $error) {
            Log::warning('ElevenLabs TTS request failed.', [
                'status' => $error->response?->status(),
                'voice_id' => $wolfiVoiceId ?: $usedProviderVoiceId,
                'error_code' => data_get($error->response?->json(), 'detail.code'),
                'model' => $model,
            ]);

            throw new RuntimeException('ElevenLabs TTS request failed.', 0, $error);
        } catch (Throwable $error) {
            Log::warning('ElevenLabs TTS synthesis failed.', [
                'voice_id' => $wolfiVoiceId ?: $usedProviderVoiceId,
                'model' => $model,
                'message' => $error->getMessage(),
            ]);

            throw new RuntimeException('ElevenLabs TTS synthesis failed.', 0, $error);
        }

        return [
            'audio' => $response->body(),
            'content_type' => $response->header('Content-Type') ?: 'audio/mpeg',
            'locale' => $this->speechLocale($localeBase),
            'voice' => $wolfiVoiceId ?: $providerVoiceId,
            'model' => $model,
            'provider' => 'elevenlabs',
        ];
    }

    private function sendRequest(string $voiceId, string $text, string $model, string $outputFormat): HttpResponse
    {
        return Http::baseUrl((string) config('services.elevenlabs.base_url', 'https://api.elevenlabs.io'))
            ->withHeaders([
                'xi-api-key' => (string) config('services.elevenlabs.api_key'),
                'Accept' => 'audio/mpeg',
            ])
            ->timeout((int) config('services.elevenlabs.timeout', 20))
            ->asJson()
            ->post('v1/text-to-speech/'.rawurlencode($voiceId).'?output_format='.rawurlencode($outputFormat), [
                'text' => $text,
                'model_id' => $model,
            ]);
    }

    private function shouldRetryWithFallbackVoice(HttpResponse $response, string $providerVoiceId): bool
    {
        $fallbackVoiceId = trim((string) config('services.elevenlabs.fallback_voice_id', ''));

        if ($fallbackVoiceId === '' || $fallbackVoiceId === $providerVoiceId) {
            return false;
        }

        return $response->status() === 402
            && data_get($response->json(), 'detail.code') === 'paid_plan_required';
    }

    private function normalizeLocale(?string $locale): string
    {
        $normalized = Str::of((string) $locale)->lower()->replace('_', '-')->before('-')->value();

        return in_array($normalized, ['de', 'es', 'fr', 'hi', 'it', 'pt'], true) ? $normalized : 'en';
    }

    private function speechLocale(string $localeBase): string
    {
        return match ($localeBase) {
            'de' => 'de-DE',
            'es' => 'es-ES',
            'fr' => 'fr-FR',
            'hi' => 'hi-IN',
            'it' => 'it-IT',
            'pt' => 'pt-PT',
            default => 'en-US',
        };
    }
}
