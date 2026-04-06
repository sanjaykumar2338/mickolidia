<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class PublicContentIndex
{
    public function siteSearchIndex(?string $locale = null): array
    {
        return $this->localizedKnowledgeIndex($this->normalizeLocale($locale));
    }

    public function voiceAssistantIndex(?array $locales = null, array $speechLocaleMap = []): array
    {
        $locales = $locales ?: array_keys(config('wolforix.supported_locales', []));
        $items = [];

        foreach ($locales as $locale) {
            $locale = $this->normalizeLocale($locale);
            $speechLocale = $speechLocaleMap[$locale] ?? strtoupper($locale);

            foreach ($this->localizedKnowledgeIndex($locale) as $item) {
                $answer = trim((string) ($item['answer'] ?? $item['description'] ?? ''));

                if ($answer === '' || trim((string) ($item['title'] ?? '')) === '') {
                    continue;
                }

                $items[] = [
                    'locale' => $locale,
                    'speech_locale' => $speechLocale,
                    'section' => $item['section'] ?? '',
                    'question' => $item['title'],
                    'answer' => Str::limit($answer, 420),
                    'url' => $item['url'] ?? '',
                    'search_text' => trim(implode(' ', array_filter([
                        $item['keywords'] ?? '',
                        $item['search_text'] ?? '',
                        $answer,
                    ]))),
                ];
            }
        }

        return $items;
    }

    private function localizedKnowledgeIndex(string $locale): array
    {
        $searchLabels = $this->translatedArray('site.search.section_labels', $locale);
        $faqSections = $this->translatedArray('site.faq.sections', $locale);
        $securitySections = $this->translatedArray('site.security.sections', $locale);
        $legalPages = $this->translatedArray('site.legal.pages', $locale);
        $aboutSearchContent = [
            Lang::get('site.home.about.eyebrow', [], $locale),
            Lang::get('site.home.about.title', [], $locale),
            Lang::get('site.home.about.intro', [], $locale),
            Lang::get('site.home.about.pillars', [], $locale),
        ];
        $homeSearchContent = [
            Lang::get('site.home.eyebrow', [], $locale),
            Lang::get('site.home.title', [], $locale),
            Lang::get('site.home.description', [], $locale),
            Lang::get('site.home.mobile_title', [], $locale),
            Lang::get('site.home.mobile_description', [], $locale),
            Lang::get('site.home.primary_cta', [], $locale),
            Lang::get('site.home.free_trial_cta', [], $locale),
            Lang::get('site.home.free_trial_caption', [], $locale),
            Lang::get('site.home.secondary_cta', [], $locale),
            Lang::get('site.home.badges', [], $locale),
            Lang::get('site.home.feature_cards', [], $locale),
            Lang::get('site.home.trust', [], $locale),
            Lang::get('site.home.hero_visual', [], $locale),
            [
                Lang::get('site.home.challenge_selector.currency_label', [], $locale),
                Lang::get('site.home.challenge_selector.type_label', [], $locale),
                Lang::get('site.home.challenge_selector.size_label', [], $locale),
                Lang::get('site.home.challenge_selector.insight_title', [], $locale),
                Lang::get('site.home.challenge_selector.entry_fee', [], $locale),
                Lang::get('site.home.challenge_selector.current_price', [], $locale),
                Lang::get('site.home.challenge_selector.original_price', [], $locale),
                Lang::get('site.home.challenge_selector.start_button', [], $locale),
                Lang::get('site.home.challenge_selector.review_policy', [], $locale),
                Lang::get('site.home.challenge_selector.faq_link', [], $locale),
                Lang::get('site.home.challenge_selector.currencies', [], $locale),
                Lang::get('site.home.challenge_selector.phase_titles', [], $locale),
                Lang::get('site.home.challenge_selector.metrics', [], $locale),
                Lang::get('site.home.challenge_selector.value_templates', [], $locale),
                Lang::get('site.home.challenge_selector.consistency_required', [], $locale),
                array_map(static fn (array $type): array => [
                    'label' => $type['label'] ?? '',
                    'description' => $type['description'] ?? '',
                ], $this->translatedArray('site.home.challenge_selector.types', $locale)),
            ],
            [
                Lang::get('site.home.plans.eyebrow', [], $locale),
                Lang::get('site.home.plans.title', [], $locale),
                Lang::get('site.home.plans.description', [], $locale),
                Lang::get('site.home.plans.platform_label', [], $locale),
                Lang::get('site.home.plans.platform_value', [], $locale),
                Lang::get('site.home.plans.entry_fee', [], $locale),
                Lang::get('site.home.plans.profit_target', [], $locale),
                Lang::get('site.home.plans.daily_loss', [], $locale),
                Lang::get('site.home.plans.max_loss', [], $locale),
                Lang::get('site.home.plans.steps', [], $locale),
                Lang::get('site.home.plans.profit_share', [], $locale),
                Lang::get('site.home.plans.first_payout', [], $locale),
                Lang::get('site.home.plans.minimum_days', [], $locale),
            ],
            Lang::get('site.home.global_reach', [], $locale),
            Lang::get('site.home.market_pulse', [], $locale),
        ];
        $items = [];

        $items[] = $this->makeItem(
            section: $searchLabels['page'] ?? 'Page',
            title: Lang::get('site.nav.plans', [], $locale),
            description: Lang::get('site.home.plans.description', [], $locale),
            url: route('home').'#plans',
            answer: Lang::get('site.home.plans.description', [], $locale),
            keywords: implode(' ', $this->collectStrings([
                Lang::get('site.home.plans.eyebrow', [], $locale),
                Lang::get('site.home.plans.title', [], $locale),
                Lang::get('site.home.plans.platform_value', [], $locale),
                Lang::get('site.home.challenge_selector.highlights', [], $locale),
            ])),
            searchText: $this->searchableText($homeSearchContent),
        );

        $items[] = $this->makeItem(
            section: $searchLabels['page'] ?? 'Page',
            title: Lang::get('site.nav.about_us', [], $locale),
            description: Lang::get('site.home.about.intro', [], $locale),
            url: route('about'),
            answer: Lang::get('site.home.about.intro', [], $locale),
            searchText: $this->searchableText($aboutSearchContent),
        );

        $items[] = $this->makeItem(
            section: $searchLabels['page'] ?? 'Page',
            title: Lang::get('site.nav.security', [], $locale),
            description: Lang::get('site.security.description', [], $locale),
            url: route('security'),
            answer: Lang::get('site.security.description', [], $locale),
            keywords: 'security 2fa encryption account protection iso 27001 risk data protection',
            searchText: $this->searchableText([
                Lang::get('site.security', [], $locale),
            ]),
        );

        foreach ($securitySections as $section) {
            $sectionTitle = trim((string) ($section['title'] ?? ''));

            if ($sectionTitle === '') {
                continue;
            }

            $sectionSummary = $this->buildSummary([
                $section['description'] ?? '',
                $section['items'] ?? [],
            ], 180);

            $items[] = $this->makeItem(
                section: $searchLabels['page'] ?? 'Page',
                title: $sectionTitle,
                description: $sectionSummary,
                url: route('security'),
                answer: $this->searchableText([
                    $section['description'] ?? '',
                    $section['items'] ?? [],
                ]),
                keywords: Lang::get('site.nav.security', [], $locale),
                searchText: $this->searchableText([
                    Lang::get('site.security.title', [], $locale),
                    Lang::get('site.security.description', [], $locale),
                    $section,
                ]),
            );
        }

        $items[] = $this->makeItem(
            section: $searchLabels['support'] ?? 'Support',
            title: Lang::get('site.nav.contact', [], $locale),
            description: Lang::get('site.contact.description', [], $locale),
            url: route('contact'),
            answer: Lang::get('site.contact.description', [], $locale),
            searchText: $this->searchableText([
                Lang::get('site.contact', [], $locale),
            ]),
        );

        $items[] = $this->makeItem(
            section: $searchLabels['page'] ?? 'Page',
            title: Lang::get('site.nav.faq', [], $locale),
            description: Lang::get('site.faq.description', [], $locale),
            url: route('faq'),
            answer: Lang::get('site.faq.description', [], $locale),
            searchText: $this->searchableText([
                Lang::get('site.faq', [], $locale),
            ]),
        );

        $items[] = $this->makeItem(
            section: $searchLabels['page'] ?? 'Page',
            title: Lang::get('site.nav.news', [], $locale),
            description: Lang::get('site.news.description', [], $locale),
            url: route('news'),
            answer: Lang::get('site.news.description', [], $locale),
            keywords: implode(' ', $this->collectStrings([
                Lang::get('site.home.market_pulse.title', [], $locale),
                Lang::get('site.home.market_pulse.description', [], $locale),
                Lang::get('site.home.market_pulse.cards', [], $locale),
            ])),
            searchText: $this->searchableText([
                Lang::get('site.news', [], $locale),
                Lang::get('site.home.market_pulse', [], $locale),
            ]),
        );

        foreach (config('wolforix.legal_pages', []) as $slug => $configPage) {
            $contentKey = $configPage['content_key'] ?? null;
            $routeName = $configPage['route_name'] ?? null;
            $page = is_string($contentKey) ? ($legalPages[$contentKey] ?? null) : null;

            if (! is_array($page) || ! is_string($routeName) || ! \Route::has($routeName)) {
                continue;
            }

            $pageTitle = trim((string) ($page['title'] ?? ''));

            if ($pageTitle === '') {
                continue;
            }

            $pageUrl = route($routeName);
            $pageSummary = $this->buildSummary([
                $page['intro'] ?? '',
                $page['highlight'] ?? [],
            ], 180);

            $items[] = $this->makeItem(
                section: $searchLabels['policy'] ?? 'Policy',
                title: $pageTitle,
                description: $pageSummary,
                url: $pageUrl,
                answer: $this->searchableText([
                    $page['intro'] ?? '',
                    $page['highlight'] ?? [],
                ]),
                searchText: $this->searchableText([
                    $page,
                ]),
            );

            foreach ($page['sections'] ?? [] as $section) {
                $sectionTitle = trim((string) ($section['title'] ?? ''));

                if ($sectionTitle === '') {
                    continue;
                }

                $sectionUrl = $pageUrl.'#'.$this->sectionAnchor($sectionTitle);
                $sectionSummary = $this->buildSummary([
                    $section['paragraphs'] ?? [],
                    $section['bullets'] ?? [],
                ], 180);

                $items[] = $this->makeItem(
                    section: $searchLabels['policy'] ?? 'Policy',
                    title: $sectionTitle,
                    description: $sectionSummary,
                    url: $sectionUrl,
                    answer: $this->searchableText([
                        $section['paragraphs'] ?? [],
                        $section['bullets'] ?? [],
                    ]),
                    keywords: implode(' ', array_filter([
                        $pageTitle,
                        $page['intro'] ?? '',
                    ])),
                    searchText: $this->searchableText([
                        $pageTitle,
                        $page['intro'] ?? '',
                        $section,
                    ]),
                );
            }
        }

        foreach ($faqSections as $section) {
            $sectionTitle = $section['title'] ?? '';

            foreach ($section['items'] ?? [] as $item) {
                $question = trim((string) ($item['question'] ?? ''));

                if ($question === '') {
                    continue;
                }

                $answer = $this->faqAnswer($item);

                $items[] = $this->makeItem(
                    section: $searchLabels['faq'] ?? 'FAQ',
                    title: $question,
                    description: Str::limit($answer, 180),
                    url: route('faq'),
                    answer: $answer,
                    keywords: $sectionTitle,
                    searchText: $this->searchableText([
                        $sectionTitle,
                        $item,
                    ]),
                );
            }
        }

        return array_values(array_filter($items));
    }

    private function makeItem(
        string $section,
        string $title,
        string $description,
        string $url,
        string $answer = '',
        string $keywords = '',
        string $searchText = '',
    ): array {
        return [
            'section' => trim($section),
            'title' => trim($title),
            'description' => trim($description),
            'answer' => trim($answer),
            'url' => trim($url),
            'keywords' => trim($keywords),
            'search_text' => trim($searchText),
        ];
    }

    private function normalizeLocale(?string $locale): string
    {
        $locale = strtolower(trim((string) ($locale ?: app()->getLocale())));

        return array_key_exists($locale, config('wolforix.supported_locales', []))
            ? $locale
            : (string) config('wolforix.default_locale', 'en');
    }

    private function buildSummary(array $content, int $limit = 180): string
    {
        return Str::limit($this->searchableText($content), $limit);
    }

    private function faqAnswer(array $item): string
    {
        return $this->searchableText([
            $item['answer'] ?? '',
            $item['answer_paragraphs'] ?? [],
            $item['answer_sections'] ?? [],
        ]);
    }

    private function searchableText(array $content): string
    {
        $segments = array_values(array_unique($this->collectStrings($content)));

        return trim(implode(' ', $segments));
    }

    private function collectStrings(mixed $value): array
    {
        if (is_string($value) || is_numeric($value)) {
            $text = trim(strip_tags((string) $value));

            return $text === '' ? [] : [$text];
        }

        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $child) {
            $strings = [...$strings, ...$this->collectStrings($child)];
        }

        return $strings;
    }

    private function translatedArray(string $key, string $locale): array
    {
        $value = Lang::get($key, [], $locale);

        return is_array($value) ? $value : [];
    }

    private function sectionAnchor(string $title): string
    {
        return Str::slug($title);
    }
}
