@php
    $paymentMethods = [
        [
            'key' => 'stripe',
            'label' => 'Stripe',
            'type' => 'image',
            'src' => asset('branding/stripe-logo.svg'),
            'alt' => 'Stripe',
        ],
        [
            'key' => 'paypal',
            'label' => 'PayPal',
            'type' => 'image',
            'src' => asset('branding/paypal-logo.png'),
            'alt' => 'PayPal',
        ],
        [
            'key' => 'visa',
            'label' => 'Visa',
            'type' => 'image',
            'src' => asset('mickolidia-attachments/IMG_8996.jpeg'),
            'alt' => 'Visa',
        ],
        [
            'key' => 'mastercard',
            'label' => 'Mastercard',
            'type' => 'mastercard',
        ],
        [
            'key' => 'apple-pay',
            'label' => 'Apple Pay',
            'type' => 'apple_pay',
        ],
        [
            'key' => 'google-pay',
            'label' => 'Google Pay',
            'type' => 'google_pay',
        ],
    ];

    $paymentRail = array_merge($paymentMethods, $paymentMethods);
    $positioningBullets = trans('site.footer.positioning_bullets');
    $positioningBullets = is_array($positioningBullets) ? $positioningBullets : [];
    $communityImageLocale = explode('-', strtolower(str_replace('_', '-', app()->getLocale())))[0] ?: 'en';
    $communityImageSets = [
        'en' => [
            'instagram' => 'photo/mickolidia-attachments-englsih/14A600E1-31EF-45B2-9371-117EE60A3E51.png',
            'telegram' => 'photo/mickolidia-attachments-englsih/866B008F-4AC9-4341-ADBB-74AF5B7C0584.png',
            'whatsapp' => 'photo/mickolidia-attachments-englsih/7C41556E-3E1A-407A-97CD-22A84970EA82.png',
            'youtube' => 'photo/mickolidia-attachments-englsih/DECE0553-9101-497D-AF85-8B62BB21A3EA.png',
        ],
        'es' => [
            'instagram' => 'photo/mickolidia-attachments-spanish/4D02D06C-F184-40F3-A843-11FCB7F7BB9D.png',
            'telegram' => 'photo/mickolidia-attachments-spanish/2E180800-8FFA-44FA-B231-C84F32D425C8.png',
            'whatsapp' => 'photo/mickolidia-attachments-spanish/3D3A8551-F94D-4B95-AD71-51B381E7D2A5.png',
            'youtube' => 'photo/mickolidia-attachments-spanish/A3EBA35E-6E4D-42C6-86C3-60261AB729ED.png',
        ],
    ];
    $communityImages = $communityImageSets[$communityImageLocale] ?? $communityImageSets['en'];

    $communityLinks = [
        [
            'key' => 'instagram',
            'platform' => 'Instagram',
            'badge' => 'Primary Entry',
            'title' => 'Start here',
            'description' => 'Discover Wolforix and stay connected',
            'url' => 'https://www.instagram.com/wolforix?igsh=djA4NHZicW5oam96&utm_source=qr',
            'image' => asset($communityImages['instagram']),
            'primary' => true,
        ],
        [
            'key' => 'telegram',
            'platform' => 'Telegram',
            'title' => 'English Community',
            'suffix_html' => '&#127468;&#127463;',
            'description' => 'Access daily market insights',
            'url' => 'https://t.me/wolforix',
            'image' => asset($communityImages['telegram']),
        ],
        [
            'key' => 'whatsapp',
            'platform' => 'WhatsApp',
            'title' => 'Spanish-Speaking Community',
            'description_html' => 'Accede a informaci&oacute;n diaria de mercado',
            'url' => 'https://chat.whatsapp.com/KSvvnEQFDUgKDM8EQXNWIT?mode=gi_t',
            'image' => asset($communityImages['whatsapp']),
        ],
        [
            'key' => 'youtube',
            'platform' => 'YouTube',
            'title' => 'Media & Promotions',
            'description' => 'Latest updates and announcements',
            'url' => 'https://youtube.com/@wolforix?si=NtJ-jmS20024s7m3',
            'image' => asset($communityImages['youtube']),
        ],
    ];

    $trustItems = trans('site.home.trust.items');
    $primaryTrustItems = array_slice(is_array($trustItems) ? $trustItems : [], 0, 4);
    $secondaryTrustItems = trans('site.home.trust.support_items');
    $secondaryTrustItems = is_array($secondaryTrustItems) ? $secondaryTrustItems : [];
    $trustIcons = [
        <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75c-1.94 1.24-4.47 1.88-7.5 1.88v5.25c0 4.96 3.11 8.1 7.5 9.37 4.39-1.27 7.5-4.41 7.5-9.37V5.63c-3.03 0-5.56-.64-7.5-1.88Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 11.25 1.5 1.5 3-3.75" />
            </svg>
        SVG,
        <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 12A4.5 4.5 0 0 1 12 7.5h7.5" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 4.5 19.5 7.5 16.5 10.5" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12A4.5 4.5 0 0 1 12 16.5H4.5" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 19.5 4.5 16.5 7.5 13.5" />
            </svg>
        SVG,
        <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75v16.5" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h9v9h-9z" />
            </svg>
        SVG,
        <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9Z" />
            </svg>
        SVG,
    ];
@endphp

<section class="px-6 pb-6 pt-0 lg:px-8 lg:pb-8 lg:pt-4">
    <div class="mx-auto max-w-7xl">
        <div class="prefooter-showcase space-y-4">
            <div class="payment-rail-floating relative overflow-hidden px-1 py-4">
                <div class="prefooter-orb prefooter-orb-left" aria-hidden="true"></div>
                <div class="prefooter-orb prefooter-orb-right" aria-hidden="true"></div>

                <div class="relative z-10">
                    <div class="payment-rail-mask">
                        <div class="payment-rail-track" aria-label="{{ __('site.footer.payments.title') }}">
                            @foreach ($paymentRail as $paymentMethod)
                                <div class="payment-mark payment-mark--{{ $paymentMethod['key'] }}">
                                    @if (($paymentMethod['type'] ?? '') === 'image')
                                        <img src="{{ $paymentMethod['src'] }}" alt="{{ $paymentMethod['alt'] ?? $paymentMethod['label'] }}" class="block h-5 w-auto object-contain sm:h-6">
                                    @elseif (($paymentMethod['type'] ?? '') === 'mastercard')
                                        <span class="payment-mastercard-mark" aria-hidden="true">
                                            <span class="payment-mastercard-circle payment-mastercard-circle--left"></span>
                                            <span class="payment-mastercard-circle payment-mastercard-circle--right"></span>
                                        </span>
                                        <span class="payment-wordmark payment-wordmark--mastercard">{{ $paymentMethod['label'] }}</span>
                                    @elseif (($paymentMethod['type'] ?? '') === 'apple_pay')
                                        <span class="payment-apple-icon" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M16.86 12.54c.03 3.1 2.72 4.13 2.75 4.14-.02.07-.43 1.49-1.41 2.95-.85 1.26-1.73 2.51-3.12 2.53-1.36.03-1.8-.81-3.36-.81-1.56 0-2.05.79-3.34.84-1.34.05-2.37-1.34-3.22-2.59C3.43 17.91 2.1 14.93 3.86 11.9c.87-1.5 2.42-2.45 4.11-2.47 1.29-.03 2.5.87 3.36.87.86 0 2.47-1.07 4.16-.91.71.03 2.7.29 3.98 2.16-.1.06-2.38 1.39-2.35 4.13Zm-2.81-5.16c.71-.86 1.19-2.05 1.06-3.24-1.03.04-2.29.69-3.03 1.55-.66.76-1.24 1.97-1.09 3.14 1.15.09 2.35-.58 3.06-1.45Z"/>
                                            </svg>
                                        </span>
                                        <span class="payment-wordmark payment-wordmark--apple">{{ $paymentMethod['label'] }}</span>
                                    @elseif (($paymentMethod['type'] ?? '') === 'google_pay')
                                        <span class="payment-google-g" aria-hidden="true">G</span>
                                        <span class="payment-wordmark payment-wordmark--google">{{ $paymentMethod['label'] }}</span>
                                    @else
                                        <span class="payment-wordmark">{{ $paymentMethod['label'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @if ($positioningBullets !== [])
                <div class="grid gap-3 md:grid-cols-3">
                    @foreach ($positioningBullets as $bullet)
                        <div class="rounded-[1.45rem] border border-amber-400/14 bg-amber-400/8 px-5 py-4 shadow-[0_18px_55px_rgba(2,6,23,0.2)]">
                            <div class="flex items-start gap-3">
                                <span class="mt-1 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-amber-300/25 bg-amber-300/12 text-amber-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.79 6.8-6.79a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                                <p class="text-sm font-semibold leading-6 text-white sm:text-base">{{ $bullet }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="surface-panel relative overflow-hidden rounded-[2.4rem] p-5 sm:p-6">
                <div class="prefooter-orb prefooter-orb-bottom" aria-hidden="true"></div>

                <div class="relative z-10 max-w-3xl">
                    <span class="section-label">{{ __('site.footer.community.eyebrow') }}</span>
                    <h2 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.footer.community.title') }}</h2>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-400 sm:text-base">{{ __('site.footer.community.description') }}</p>
                </div>

                <div class="community-access-grid relative z-10 mt-8">
                    @foreach ($communityLinks as $communityLink)
                        @php($isPrimaryCommunityLink = ! empty($communityLink['primary']))
                        <a
                            href="{{ $communityLink['url'] }}"
                            target="_blank"
                            rel="noreferrer"
                            aria-label="{{ $communityLink['platform'] }} - {{ $communityLink['title'] }}"
                            class="community-access-card community-access-card--{{ $communityLink['key'] }} {{ $isPrimaryCommunityLink ? 'community-access-card--primary' : '' }} group"
                        >
                            <span class="community-access-platform">
                                <span>{{ $communityLink['platform'] }}</span>

                                @if (! empty($communityLink['badge']))
                                    <span class="community-access-badge">{{ $communityLink['badge'] }}</span>
                                @endif
                            </span>

                            <span class="community-access-media" aria-hidden="true">
                                <img
                                    src="{{ $communityLink['image'] }}"
                                    alt=""
                                    class="community-access-image"
                                    decoding="async"
                                    loading="lazy"
                                >
                            </span>

                            <span class="community-access-copy">
                                <span class="community-access-title">
                                    {{ $communityLink['title'] }}

                                    @if (! empty($communityLink['suffix_html']))
                                        <span class="community-access-title-suffix" aria-hidden="true">{!! $communityLink['suffix_html'] !!}</span>
                                    @endif
                                </span>

                                <span class="community-access-description">
                                    @if (! empty($communityLink['description_html']))
                                        {!! $communityLink['description_html'] !!}
                                    @else
                                        {{ $communityLink['description'] }}
                                    @endif
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="surface-panel relative overflow-hidden rounded-[2.2rem] p-5 sm:p-6">
                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.18fr)_minmax(17rem,0.74fr)] xl:items-start">
                    <div class="max-w-3xl">
                        <span class="section-label">{{ __('site.home.trust.eyebrow') }}</span>
                        <h2 class="mt-4 text-2xl font-semibold text-white sm:text-[2rem]">{{ __('site.home.trust.title') }}</h2>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-400">{{ __('site.home.trust.description') }}</p>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            @foreach ($primaryTrustItems as $item)
                                <article class="rounded-[1.45rem] border border-white/8 bg-white/3 p-4">
                                    <div class="flex items-start gap-3">
                                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-emerald-400/18 bg-emerald-500/10 text-emerald-100">
                                            {!! $trustIcons[$loop->index] ?? $trustIcons[0] !!}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold leading-6 text-white">{{ $item['title'] }}</p>
                                            <p class="mt-1 text-xs leading-5 text-slate-400">{{ $item['description'] }}</p>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-[1.6rem] border border-white/8 bg-white/3 p-5 xl:max-w-sm xl:justify-self-end">
                        <a href="{{ route('security') }}" class="inline-flex rounded-full border border-white/10 px-5 py-2.5 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                            {{ __('site.home.trust.cta') }}
                        </a>

                        @if ($secondaryTrustItems !== [])
                            <ul class="mt-4 space-y-3">
                                @foreach ($secondaryTrustItems as $item)
                                    <li class="flex items-start gap-3 text-xs leading-5 text-slate-400">
                                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-300"></span>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
