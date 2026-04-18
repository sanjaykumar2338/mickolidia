<?php

namespace App\Services\Wolfi;

class WolfiKnowledgeBase
{
    /**
     * @return array<string, mixed>
     */
    public function assistantMeta(): array
    {
        return (array) config('wolfi.assistant', []);
    }

    /**
     * @return list<array<string, string>>
     */
    public function pillars(): array
    {
        return array_values((array) config('wolfi.pillars', []));
    }

    /**
     * @return list<array<string, string>>
     */
    public function quickActions(): array
    {
        return array_values((array) config('wolfi.quick_actions', []));
    }

    /**
     * @return array<string, mixed>
     */
    public function pageGuide(string $page): array
    {
        $guide = config("wolfi.pages.{$page}");

        if (! is_array($guide)) {
            $guide = (array) config('wolfi.pages.dashboard', []);
        }

        return $guide;
    }

    /**
     * @return array<string, mixed>
     */
    public function voiceMeta(): array
    {
        return (array) config('wolfi.voice', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function supportMeta(): array
    {
        return [
            'email' => (string) config('wolforix.support.email'),
            'business_hours' => (string) config('wolforix.support.business_hours'),
            'common_topics' => array_values((array) config('wolfi.support.common_topics', [])),
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
        return array_values((array) config('wolfi.rules.pass_fail_items', []));
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
                'label' => 'Overview',
            ],
            [
                'key' => 'dashboard.accounts',
                'label' => 'Accounts',
            ],
            [
                'key' => 'dashboard.payouts',
                'label' => 'Payouts',
            ],
            [
                'key' => 'dashboard.settings',
                'label' => 'Settings',
            ],
        ];
    }
}
