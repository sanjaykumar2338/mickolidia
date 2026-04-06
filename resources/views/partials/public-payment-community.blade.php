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
            'type' => 'wordmark',
        ],
        [
            'key' => 'mastercard',
            'label' => 'Mastercard',
            'type' => 'mastercard',
        ],
        [
            'key' => 'cards',
            'label' => __('site.footer.payments.cards_label'),
            'type' => 'supporting',
        ],
        [
            'key' => 'protected',
            'label' => __('site.footer.payments.protected_label'),
            'type' => 'supporting',
        ],
    ];

    $paymentRail = array_merge($paymentMethods, $paymentMethods);

    $communityLinks = [
        [
            'key' => 'youtube',
            'title' => 'YouTube',
            'url' => 'https://youtube.com/@wolforix?si=NtJ-jmS20024s7m3',
            'description' => __('site.footer.community.channels.youtube.description'),
            'cta' => __('site.footer.community.channels.youtube.cta'),
            'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M21.58 7.19a2.98 2.98 0 0 0-2.1-2.1C17.62 4.6 12 4.6 12 4.6s-5.62 0-7.48.49a2.98 2.98 0 0 0-2.1 2.1A31.4 31.4 0 0 0 1.93 12c0 1.61.16 3.22.49 4.81a2.98 2.98 0 0 0 2.1 2.1c1.86.49 7.48.49 7.48.49s5.62 0 7.48-.49a2.98 2.98 0 0 0 2.1-2.1c.33-1.59.49-3.2.49-4.81 0-1.61-.16-3.22-.49-4.81ZM10.2 15.01V8.99L15.4 12l-5.2 3.01Z"/>
                </svg>
            SVG,
        ],
        [
            'key' => 'instagram',
            'title' => 'Instagram',
            'url' => 'https://www.instagram.com/wolforix?igsh=djA4NHZicW5oam96&utm_source=qr',
            'description' => __('site.footer.community.channels.instagram.description'),
            'cta' => __('site.footer.community.channels.instagram.cta'),
            'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <rect x="3.75" y="3.75" width="16.5" height="16.5" rx="4.5" />
                    <circle cx="12" cy="12" r="3.75" />
                    <circle cx="17.2" cy="6.8" r="0.9" fill="currentColor" stroke="none" />
                </svg>
            SVG,
        ],
        [
            'key' => 'telegram',
            'title' => 'Telegram',
            'url' => 'https://t.me/wolforix',
            'description' => __('site.footer.community.channels.telegram.description'),
            'cta' => __('site.footer.community.channels.telegram.cta'),
            'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="m20.67 4.37-2.91 14.1c-.22 1-.8 1.24-1.62.77l-4.48-3.3-2.16 2.08c-.24.24-.44.44-.9.44l.32-4.56 8.3-7.5c.36-.32-.08-.5-.56-.18L6.71 12.7l-4.41-1.38c-.96-.3-.98-.96.2-1.42L19.8 3.23c.8-.3 1.5.18 1.24 1.14Z"/>
                </svg>
            SVG,
        ],
    ];
@endphp

<section class="px-6 pb-8 pt-6 lg:px-8 lg:pb-10">
    <div class="mx-auto max-w-7xl">
        <div class="prefooter-showcase space-y-6">
            <div class="surface-panel relative overflow-hidden rounded-[2.4rem] p-6 sm:p-8">
                <div class="prefooter-orb prefooter-orb-left" aria-hidden="true"></div>
                <div class="prefooter-orb prefooter-orb-right" aria-hidden="true"></div>

                <div class="relative z-10">
                    <div class="max-w-3xl">
                        <span class="section-label">{{ __('site.footer.payments.eyebrow') }}</span>
                        <h2 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.footer.payments.title') }}</h2>
                        <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                            {{ __('site.footer.payments.description') }}
                        </p>
                    </div>

                    <div class="payment-rail-shell mt-8">
                        <div class="payment-rail-mask">
                            <div class="payment-rail-track" aria-label="{{ __('site.footer.payments.title') }}">
                                @foreach ($paymentRail as $paymentMethod)
                                    <div class="payment-chip payment-chip--{{ $paymentMethod['key'] }}">
                                        @if (($paymentMethod['type'] ?? '') === 'image')
                                            <img src="{{ $paymentMethod['src'] }}" alt="{{ $paymentMethod['alt'] ?? $paymentMethod['label'] }}" class="block h-5 w-auto object-contain">
                                        @elseif (($paymentMethod['type'] ?? '') === 'mastercard')
                                            <span class="payment-mastercard-mark" aria-hidden="true">
                                                <span class="payment-mastercard-circle payment-mastercard-circle--left"></span>
                                                <span class="payment-mastercard-circle payment-mastercard-circle--right"></span>
                                            </span>
                                            <span>{{ $paymentMethod['label'] }}</span>
                                        @else
                                            <span>{{ $paymentMethod['label'] }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="surface-panel relative overflow-hidden rounded-[2.4rem] p-6 sm:p-8">
                <div class="prefooter-orb prefooter-orb-bottom" aria-hidden="true"></div>

                <div class="relative z-10 max-w-3xl">
                    <span class="section-label">{{ __('site.footer.community.eyebrow') }}</span>
                    <h2 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.footer.community.title') }}</h2>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                        {{ __('site.footer.community.description') }}
                    </p>
                </div>

                <div class="community-grid relative z-10 mt-8 grid gap-4 lg:grid-cols-3">
                    @foreach ($communityLinks as $communityLink)
                        <a
                            href="{{ $communityLink['url'] }}"
                            target="_blank"
                            rel="noreferrer"
                            class="community-card community-card--{{ $communityLink['key'] }} group rounded-[1.8rem] p-5 sm:p-6"
                        >
                            <span class="community-card-icon">
                                {!! $communityLink['icon'] !!}
                            </span>
                            <p class="mt-5 text-xl font-semibold text-white">{{ $communityLink['title'] }}</p>
                            <p class="mt-3 text-sm leading-7 text-slate-300">{{ $communityLink['description'] }}</p>
                            <span class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-white transition group-hover:translate-x-1">
                                {{ $communityLink['cta'] }}
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                                </svg>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
