<?php

namespace App\Services\Voice;

use App\Services\Wolfi\WolfiVoiceSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenAiTextToSpeechService
{
    public function __construct(
        private readonly WolfiVoiceSettings $wolfiVoiceSettings,
    ) {}

    public function isConfigured(): bool
    {
        return (bool) config('services.openai.tts.enabled', true)
            && filled(config('services.openai.api_key'));
    }

    /**
     * @return array{audio: string, content_type: string, locale: string, voice: string, model: string}
     */
    public function synthesize(string $text, ?string $locale = null, ?string $voiceId = null): array
    {
        $localeBase = $this->normalizeLocale($locale);
        $voice = $this->wolfiVoiceSettings->resolveVoiceId($voiceId);
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
                'voice' => $voice,
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
            'voice' => $voice,
            'model' => $model,
        ];
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
