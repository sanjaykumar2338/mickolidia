<?php

namespace App\Services\Voice;

use App\Services\Wolfi\WolfiVoiceSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OpenAiTextToSpeechService
{
    public function __construct(
        private readonly WolfiVoiceSettings $wolfiVoiceSettings,
    ) {}

    public function isConfigured(?string $voiceId = null): bool
    {
        $voice = $this->resolveVoiceDefinition($voiceId);

        return match ($voice['provider']) {
            'openai' => (bool) config('services.openai.tts.enabled', true)
                && filled(config('services.openai.api_key')),
            'elevenlabs' => (bool) config('services.elevenlabs.tts.enabled', true)
                && filled(config('services.elevenlabs.api_key')),
            'google_cloud' => (bool) config('services.google_tts.enabled', true)
                && filled(config('services.google_tts.api_key')),
            'azure_speech' => (bool) config('services.azure_tts.enabled', true)
                && filled(config('services.azure_tts.api_key'))
                && (filled(config('services.azure_tts.region')) || filled(config('services.azure_tts.endpoint'))),
            default => false,
        };
    }

    /**
     * @return array{audio: string, content_type: string, locale: string, voice: string, model: string, provider: string}
     */
    public function synthesize(string $text, ?string $locale = null, ?string $voiceId = null): array
    {
        $voice = $this->resolveVoiceDefinition($voiceId, $locale);

        if (! $this->isConfigured($voice['id'])) {
            throw new RuntimeException('Voice preview unavailable: selected provider is not configured.');
        }

        return match ($voice['provider']) {
            'openai' => $this->synthesizeWithOpenAi($text, $locale, $voice),
            'elevenlabs' => $this->synthesizeWithElevenLabs($text, $locale, $voice),
            'google_cloud' => $this->synthesizeWithGoogleCloud($text, $locale, $voice),
            'azure_speech' => $this->synthesizeWithAzure($text, $locale, $voice),
            default => throw new RuntimeException('Voice preview unavailable for the selected provider.'),
        };
    }

    /**
     * @param array{id: string, provider: string, provider_voice_id: string} $voice
     * @return array{audio: string, content_type: string, locale: string, voice: string, model: string, provider: string}
     */
    private function synthesizeWithOpenAi(string $text, ?string $locale, array $voice): array
    {
        $localeBase = $this->normalizeLocale($locale);
        $model = (string) config('services.openai.tts.model', 'gpt-4o-mini-tts');
        $format = (string) config('services.openai.tts.format', 'mp3');
        $speed = (float) config('services.openai.tts.speed', 0.94);
        $speechLocale = $this->speechLocale($localeBase);
        $input = trim(Str::limit($text, 2000, ''));

        $response = Http::baseUrl((string) config('services.openai.base_url', 'https://api.openai.com/v1'))
            ->withToken((string) config('services.openai.api_key'))
            ->accept($this->mimeTypeForFormat($format))
            ->timeout((int) config('services.openai.timeout', 20))
            ->asJson()
            ->post('audio/speech', [
                'model' => $model,
                'voice' => $voice['provider_voice_id'],
                'input' => $input,
                'instructions' => $this->instructionsForLocale($localeBase),
                'response_format' => $format,
                'speed' => $speed,
            ])
            ->throw();

        return [
            'audio' => $response->body(),
            'content_type' => $response->header('Content-Type') ?: $this->mimeTypeForFormat($format),
            'locale' => $speechLocale,
            'voice' => $voice['id'],
            'model' => $model,
            'provider' => 'openai',
        ];
    }

    /**
     * @param array{id: string, provider: string, provider_voice_id: string} $voice
     * @return array{audio: string, content_type: string, locale: string, voice: string, model: string, provider: string}
     */
    private function synthesizeWithElevenLabs(string $text, ?string $locale, array $voice): array
    {
        $localeBase = $this->normalizeLocale($locale);
        $speechLocale = $this->speechLocale($localeBase);
        $input = trim(Str::limit($text, 2000, ''));
        $model = (string) config('services.elevenlabs.tts.model', 'eleven_multilingual_v2');
        $outputFormat = (string) config('services.elevenlabs.tts.output_format', 'mp3_44100_128');

        $response = Http::baseUrl((string) config('services.elevenlabs.base_url', 'https://api.elevenlabs.io'))
            ->withHeaders([
                'xi-api-key' => (string) config('services.elevenlabs.api_key'),
                'Accept' => 'audio/mpeg',
            ])
            ->timeout((int) config('services.elevenlabs.timeout', 20))
            ->asJson()
            ->post('v1/text-to-speech/'.rawurlencode($voice['provider_voice_id']), [
                'text' => $input,
                'model_id' => $model,
                'output_format' => $outputFormat,
            ])
            ->throw();

        return [
            'audio' => $response->body(),
            'content_type' => $response->header('Content-Type') ?: 'audio/mpeg',
            'locale' => $speechLocale,
            'voice' => $voice['id'],
            'model' => $model,
            'provider' => 'elevenlabs',
        ];
    }

    /**
     * @param array{id: string, provider: string, provider_voice_id: string} $voice
     * @return array{audio: string, content_type: string, locale: string, voice: string, model: string, provider: string}
     */
    private function synthesizeWithGoogleCloud(string $text, ?string $locale, array $voice): array
    {
        $localeBase = $this->normalizeLocale($locale);
        $speechLocale = $this->speechLocale($localeBase);
        $voiceLocale = $this->voiceLocaleFromProviderVoice($voice['provider_voice_id'], $speechLocale);
        $input = trim(Str::limit($text, 2000, ''));
        $encoding = strtoupper((string) config('services.google_tts.audio_encoding', 'MP3'));
        $endpoint = sprintf(
            '%s/v1/text:synthesize?key=%s',
            rtrim((string) config('services.google_tts.base_url', 'https://texttospeech.googleapis.com'), '/'),
            urlencode((string) config('services.google_tts.api_key')),
        );

        $response = Http::timeout((int) config('services.google_tts.timeout', 20))
            ->asJson()
            ->post($endpoint, [
                'input' => [
                    'text' => $input,
                ],
                'voice' => [
                    'languageCode' => $voiceLocale,
                    'name' => $voice['provider_voice_id'],
                ],
                'audioConfig' => [
                    'audioEncoding' => $encoding,
                ],
            ])
            ->throw();

        $audioContent = (string) data_get($response->json(), 'audioContent', '');
        $decoded = base64_decode($audioContent, true);

        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('Voice preview unavailable: Google TTS returned empty audio.');
        }

        return [
            'audio' => $decoded,
            'content_type' => $encoding === 'LINEAR16' ? 'audio/wav' : 'audio/mpeg',
            'locale' => $voiceLocale,
            'voice' => $voice['id'],
            'model' => $voice['provider_voice_id'],
            'provider' => 'google_cloud',
        ];
    }

    /**
     * @param array{id: string, provider: string, provider_voice_id: string} $voice
     * @return array{audio: string, content_type: string, locale: string, voice: string, model: string, provider: string}
     */
    private function synthesizeWithAzure(string $text, ?string $locale, array $voice): array
    {
        $localeBase = $this->normalizeLocale($locale);
        $speechLocale = $this->speechLocale($localeBase);
        $voiceLocale = $this->voiceLocaleFromProviderVoice($voice['provider_voice_id'], $speechLocale);
        $input = trim(Str::limit($text, 2000, ''));
        $region = trim((string) config('services.azure_tts.region', ''));
        $endpoint = trim((string) config('services.azure_tts.endpoint', ''));

        if ($endpoint === '' && $region !== '') {
            $endpoint = sprintf('https://%s.tts.speech.microsoft.com/cognitiveservices/v1', $region);
        }

        if ($endpoint === '') {
            throw new RuntimeException('Voice preview unavailable: Azure endpoint is missing.');
        }

        $outputFormat = (string) config('services.azure_tts.output_format', 'audio-24khz-96kbitrate-mono-mp3');
        $ssml = sprintf(
            "<speak version='1.0' xml:lang='%s'><voice xml:lang='%s' name='%s'>%s</voice></speak>",
            $voiceLocale,
            $voiceLocale,
            htmlspecialchars($voice['provider_voice_id'], ENT_XML1 | ENT_COMPAT, 'UTF-8'),
            htmlspecialchars($input, ENT_XML1 | ENT_COMPAT, 'UTF-8'),
        );

        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => (string) config('services.azure_tts.api_key'),
            'Ocp-Apim-Subscription-Region' => $region,
            'X-Microsoft-OutputFormat' => $outputFormat,
            'User-Agent' => 'Wolforix',
            'Accept' => 'audio/mpeg',
        ])
            ->withBody($ssml, 'application/ssml+xml')
            ->timeout((int) config('services.azure_tts.timeout', 20))
            ->post($endpoint)
            ->throw();

        return [
            'audio' => $response->body(),
            'content_type' => $response->header('Content-Type') ?: 'audio/mpeg',
            'locale' => $voiceLocale,
            'voice' => $voice['id'],
            'model' => $voice['provider_voice_id'],
            'provider' => 'azure_speech',
        ];
    }

    /**
     * @return array{id: string, provider: string, provider_voice_id: string}
     */
    private function resolveVoiceDefinition(?string $voiceId = null, ?string $locale = null): array
    {
        $resolvedVoiceId = $this->wolfiVoiceSettings->resolveVoiceId($voiceId);
        $voice = $this->wolfiVoiceSettings->voiceById($resolvedVoiceId);

        if (is_array($voice)) {
            return [
                'id' => $resolvedVoiceId,
                'provider' => trim((string) ($voice['provider'] ?? 'web_speech')),
                'provider_voice_id' => $this->wolfiVoiceSettings->providerVoiceId($resolvedVoiceId, $locale),
            ];
        }

        return [
            'id' => $resolvedVoiceId,
            'provider' => trim((string) config('wolfi.voices.provider', 'web_speech')),
            'provider_voice_id' => $resolvedVoiceId,
        ];
    }

    private function voiceLocaleFromProviderVoice(string $providerVoiceId, string $fallbackLocale): string
    {
        if (preg_match('/^[a-z]{2}-[A-Z]{2}/', $providerVoiceId, $matches) === 1) {
            return $matches[0];
        }

        return $fallbackLocale;
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

    private function instructionsForLocale(string $localeBase): string
    {
        $language = match ($localeBase) {
            'de' => 'German',
            'es' => 'Spanish',
            'fr' => 'French',
            'hi' => 'Hindi',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            default => 'English',
        };

        return "Speak entirely in {$language}. Use a clear, natural, fluid young male voice with warm, confident energy. Keep the pacing smooth, polished, and conversational, never robotic or stiff. Pronounce trading terms carefully, pronounce cTrader as C Trader, and read percentages and numbers naturally.";
    }

    private function mimeTypeForFormat(string $format): string
    {
        return match (strtolower($format)) {
            'wav' => 'audio/wav',
            'opus' => 'audio/opus',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            'pcm' => 'audio/pcm',
            default => 'audio/mpeg',
        };
    }
}
