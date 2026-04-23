<?php

namespace App\Services\Wolfi;

class WolfiKnowledgeBase
{
    /**
     * @return array<string, mixed>
     */
    public function assistantMeta(): array
    {
        return $this->translatedArray('assistant', (array) config('wolfi.assistant', []));
    }

    /**
     * @return list<array<string, string>>
     */
    public function pillars(): array
    {
        return array_values($this->translatedArray('pillars', (array) config('wolfi.pillars', [])));
    }

    /**
     * @return list<array<string, string>>
     */
    public function quickActions(): array
    {
        return array_values($this->translatedArray('quick_actions', (array) config('wolfi.quick_actions', [])));
    }

    /**
     * @return array<string, mixed>
     */
    public function pageGuide(string $page): array
    {
        $guide = $this->translatedArray("pages.{$page}", (array) config("wolfi.pages.{$page}", []));

        if ($guide === []) {
            $guide = $this->translatedArray('pages.dashboard', (array) config('wolfi.pages.dashboard', []));
        }

        return $guide;
    }

    /**
     * @return array<string, mixed>
     */
    public function voiceMeta(): array
    {
        return $this->translatedArray('voice', (array) config('wolfi.voice', []));
    }

    /**
     * @return array<string, mixed>
     */
    public function smartInsights(): array
    {
        return $this->translatedArray('smart_insights', (array) config('wolfi.smart_insights', []));
    }

    /**
     * @return array<string, mixed>
     */
    public function supportMeta(): array
    {
        return [
            'email' => (string) config('wolforix.support.email'),
            'business_hours' => (string) config('wolforix.support.business_hours'),
            'common_topics' => array_values($this->translatedArray('support.common_topics', (array) config('wolfi.support.common_topics', []))),
        ];
    }

    public function defaultConsistencyLimit(): float
    {
        return (float) config('wolfi.rules.default_consistency_percent', 40);
    }

    /**
     * @return list<string>
     */
    public function passFailItems(): array
    {
        return array_values($this->translatedArray('rules.pass_fail_items', (array) config('wolfi.rules.pass_fail_items', [])));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function challengeModel(string $challengeType, int $accountSize): ?array
    {
        $definition = config("wolforix.challenge_catalog.{$challengeType}.plans.{$accountSize}");

        return is_array($definition) ? $definition : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function challengeCatalog(): array
    {
        return (array) config('wolforix.challenge_catalog', []);
    }

    /**
     * @return list<array<string, string>>
     */
    public function navigationPages(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => (string) __('site.dashboard.nav.overview'),
            ],
            [
                'key' => 'dashboard.accounts',
                'label' => (string) __('site.dashboard.nav.accounts'),
            ],
            [
                'key' => 'dashboard.payouts',
                'label' => (string) __('site.dashboard.nav.payouts'),
            ],
            [
                'key' => 'dashboard.wolfi',
                'label' => (string) __('site.dashboard.nav.wolfi_hub'),
            ],
            [
                'key' => 'dashboard.wolfi.voices',
                'label' => 'Wolfi Voices',
            ],
            [
                'key' => 'dashboard.settings',
                'label' => (string) __('site.dashboard.nav.settings'),
            ],
        ];
    }

    /**
     * @return array<string|int, mixed>
     */
    private function translatedArray(string $key, array $fallback): array
    {
        $translated = trans("site.dashboard.wolfi.{$key}");

        if (! is_array($translated)) {
            return $fallback;
        }

        return array_replace_recursive($fallback, $translated);
    }
}
