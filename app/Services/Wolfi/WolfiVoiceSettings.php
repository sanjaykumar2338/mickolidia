<?php

namespace App\Services\Wolfi;

use App\Models\AppSetting;
use InvalidArgumentException;
use Illuminate\Support\Facades\Schema;

class WolfiVoiceSettings
{
    public const SELECTED_VOICE_SETTING_KEY = 'wolfi.voice.selected';

    /**
     * @return list<array{id: string, name: string, label: string, description: string}>
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

                return [
                    'id' => $id,
                    'name' => trim((string) ($voice['name'] ?? $id)),
                    'label' => trim((string) ($voice['label'] ?? '')),
                    'description' => trim((string) ($voice['description'] ?? '')),
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
        if (! Schema::hasTable('app_settings')) {
            return $this->defaultVoiceId();
        }

        $stored = AppSetting::query()->where('key', self::SELECTED_VOICE_SETTING_KEY)->first();
        $storedVoiceId = trim((string) data_get($stored?->value, 'voice_id', ''));

        if ($storedVoiceId !== '' && $this->isValidVoiceId($storedVoiceId)) {
            return $storedVoiceId;
        }

        return $this->defaultVoiceId();
    }

    /**
     * @return array{id: string, name: string, label: string, description: string}
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
        ];
    }

    public function saveSelectedVoiceId(string $voiceId): string
    {
        $voiceId = trim($voiceId);

        if (! $this->isValidVoiceId($voiceId)) {
            throw new InvalidArgumentException('Invalid Wolfi voice ID.');
        }

        if (! Schema::hasTable('app_settings')) {
            return $voiceId;
        }

        AppSetting::query()->updateOrCreate(
            ['key' => self::SELECTED_VOICE_SETTING_KEY],
            ['value' => ['voice_id' => $voiceId]],
        );

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

        return $this->voiceOptions()[0]['id'] ?? 'onyx';
    }
}
