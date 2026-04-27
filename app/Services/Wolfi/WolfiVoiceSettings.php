<?php

namespace App\Services\Wolfi;

use App\Models\AppSetting;
use InvalidArgumentException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class WolfiVoiceSettings
{
    public const SELECTED_VOICE_SETTING_KEY = 'wolfi.voice.selected';
    private const SELECTED_VOICE_CACHE_KEY = 'wolfi.voice.selected.cached';

    /**
     * @return list<array{id: string, name: string, label: string, description: string, provider: string, provider_label: string, provider_voice_id: string, locale_voice_ids: array<string, string>}>
     */
    public function voiceOptions(): array
    {
        $configured = (array) config('wolfi.voices.options', []);

        return collect($configured)
            ->map(function (mixed $voice): ?array {
                if (! is_array($voice)) {
                    return null;
                }

                $id = trim((string) ($voice['id'] ?? ''));

                if ($id === '') {
                    return null;
                }

                $localeVoiceIds = collect((array) ($voice['locale_voice_ids'] ?? []))
                    ->mapWithKeys(function (mixed $value, mixed $key): array {
                        $locale = trim(strtolower((string) $key));
                        $voiceId = trim((string) $value);

                        if ($locale === '' || $voiceId === '') {
                            return [];
                        }

                        return [$locale => $voiceId];
                    })
                    ->all();

                return [
                    'id' => $id,
                    'name' => trim((string) ($voice['name'] ?? $id)),
                    'label' => trim((string) ($voice['label'] ?? '')),
                    'description' => trim((string) ($voice['description'] ?? '')),
                    'provider' => trim((string) ($voice['provider'] ?? config('wolfi.voices.provider', 'web_speech'))),
                    'provider_label' => trim((string) ($voice['provider_label'] ?? $voice['provider'] ?? 'Web Speech API')),
                    'provider_voice_id' => trim((string) ($voice['provider_voice_id'] ?? $id)),
                    'locale_voice_ids' => $localeVoiceIds,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function voiceIds(): array
    {
        return collect($this->voiceOptions())
            ->pluck('id')
            ->values()
            ->all();
    }

    public function voiceById(string $voiceId): ?array
    {
        return collect($this->voiceOptions())
            ->first(fn (array $voice): bool => $voice['id'] === $voiceId);
    }

    public function isValidVoiceId(string $voiceId): bool
    {
        return $this->voiceById($voiceId) !== null;
    }

    public function sampleText(): string
    {
        $configured = trim((string) config('wolfi.voices.sample_text', ''));

        if ($configured !== '') {
            return $configured;
        }

        return "Hello, I'm Wolfi. I can help guide you through your dashboard, rules, payouts, and next steps.";
    }

    public function selectedVoiceId(): string
    {
        $storedVoiceId = '';

        if (Schema::hasTable('app_settings')) {
            $stored = AppSetting::query()->where('key', self::SELECTED_VOICE_SETTING_KEY)->first();
            $storedVoiceId = trim((string) data_get($stored?->value, 'voice_id', ''));
        }

        if ($storedVoiceId !== '' && $this->isValidVoiceId($storedVoiceId)) {
            return $storedVoiceId;
        }

        $cachedVoiceId = trim((string) Cache::get(self::SELECTED_VOICE_CACHE_KEY, ''));

        if ($cachedVoiceId !== '' && $this->isValidVoiceId($cachedVoiceId)) {
            return $cachedVoiceId;
        }

        return $this->defaultVoiceId();
    }

    /**
     * @return array{id: string, name: string, label: string, description: string, provider: string, provider_label: string, provider_voice_id: string}
     */
    public function selectedVoice(): array
    {
        $voice = $this->voiceById($this->selectedVoiceId());

        if ($voice !== null) {
            return $voice;
        }

        $fallbackId = $this->defaultVoiceId();

        return [
            'id' => $fallbackId,
            'name' => ucfirst($fallbackId),
            'label' => '',
            'description' => '',
            'provider' => 'web_speech',
            'provider_label' => 'Web Speech API',
            'provider_voice_id' => $fallbackId,
        ];
    }

    public function saveSelectedVoiceId(string $voiceId): string
    {
        $voiceId = trim($voiceId);

        if (! $this->isValidVoiceId($voiceId)) {
            throw new InvalidArgumentException('Invalid Wolfi voice ID.');
        }

        if (Schema::hasTable('app_settings')) {
            AppSetting::query()->updateOrCreate(
                ['key' => self::SELECTED_VOICE_SETTING_KEY],
                ['value' => ['voice_id' => $voiceId]],
            );
        }

        Cache::forever(self::SELECTED_VOICE_CACHE_KEY, $voiceId);

        return $voiceId;
    }

    public function resolveVoiceId(?string $voiceId = null): string
    {
        $candidate = trim((string) $voiceId);

        if ($candidate !== '' && $this->isValidVoiceId($candidate)) {
            return $candidate;
        }

        return $this->selectedVoiceId();
    }

    public function providerVoiceId(string $voiceId, ?string $locale = null): string
    {
        $voice = $this->voiceById($voiceId);

        if (! is_array($voice)) {
            return $voiceId;
        }

        $localeBase = $this->normalizeLocaleBase($locale);
        $localeVoiceIds = (array) ($voice['locale_voice_ids'] ?? []);

        if ($localeBase !== '' && isset($localeVoiceIds[$localeBase])) {
            $localeVoiceId = trim((string) $localeVoiceIds[$localeBase]);

            if ($localeVoiceId !== '') {
                return $localeVoiceId;
            }
        }

        $providerVoiceId = trim((string) ($voice['provider_voice_id'] ?? ''));

        if ($providerVoiceId !== '') {
            return $providerVoiceId;
        }

        return $voiceId;
    }

    private function defaultVoiceId(): string
    {
        $configured = trim((string) config('wolfi.voices.default', ''));

        if ($configured !== '' && $this->isValidVoiceId($configured)) {
            return $configured;
        }

        $legacyConfigured = trim((string) config('services.openai.tts.voice', ''));

        if ($legacyConfigured !== '' && $this->isValidVoiceId($legacyConfigured)) {
            return $legacyConfigured;
        }

        $provider = trim((string) config('wolfi.voices.provider', ''));

        if ($provider !== '') {
            $providerDefault = collect($this->voiceOptions())
                ->first(fn (array $voice): bool => $voice['provider'] === $provider);

            if (is_array($providerDefault)) {
                return $providerDefault['id'];
            }
        }

        return $this->voiceOptions()[0]['id'] ?? 'webspeech-en-guide';
    }

    private function normalizeLocaleBase(?string $locale): string
    {
        $normalized = trim(strtolower((string) $locale));

        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace('_', '-', $normalized);

        return explode('-', $normalized)[0] ?? '';
    }
}
