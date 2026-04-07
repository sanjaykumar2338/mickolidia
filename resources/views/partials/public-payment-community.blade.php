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

    $communityLinks = [
        [
            'key' => 'youtube',
            'title' => 'YouTube',
            'url' => 'https://youtube.com/@wolforix?si=NtJ-jmS20024s7m3',
            'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M21.58 7.19a2.98 2.98 0 0 0-2.1-2.1C17.62 4.6 12 4.6 12 4.6s-5.62 0-7.48.49a2.98 2.98 0 0 0-2.1 2.1A31.4 31.4 0 0 0 1.93 12c0 1.61.16 3.22.49 4.81a2.98 2.98 0 0 0 2.1 2.1c1.86.49 7.48.49 7.48.49s5.62 0 7.48-.49a2.98 2.98 0 0 0 2.1-2.1c.33-1.59.49-3.2.49-4.81 0-1.61-.16-3.22-.49-4.81ZM10.2 15.01V8.99L15.4 12l-5.2 3.01Z"/>
                </svg>
            SVG,
        ],
        [
            'key' => 'instagram',
            'title' => 'Instagram',
            'url' => 'https://www.instagram.com/wolforix?igsh=djA4NHZicW5oam96&utm_source=qr',
            'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
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
            'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="m20.67 4.37-2.91 14.1c-.22 1-.8 1.24-1.62.77l-4.48-3.3-2.16 2.08c-.24.24-.44.44-.9.44l.32-4.56 8.3-7.5c.36-.32-.08-.5-.56-.18L6.71 12.7l-4.41-1.38c-.96-.3-.98-.96.2-1.42L19.8 3.23c.8-.3 1.5.18 1.24 1.14Z"/>
                </svg>
            SVG,
        ],
        [
            'key' => 'whatsapp',
            'title' => 'WhatsApp',
            'url' => 'https://chat.whatsapp.com/KSvvnEQFDUgKDM8EQXNWIT?mode=gi_t',
            'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M19.11 4.89A9.92 9.92 0 0 0 12.04 2c-5.48 0-9.94 4.46-9.94 9.94 0 1.75.46 3.46 1.33 4.97L2 22l5.24-1.37a9.9 9.9 0 0 0 4.79 1.22h.01c5.48 0 9.94-4.46 9.94-9.94a9.86 9.86 0 0 0-2.87-7.02Zm-7.07 15.29h-.01a8.22 8.22 0 0 1-4.18-1.14l-.3-.18-3.11.81.83-3.03-.2-.31a8.24 8.24 0 0 1-1.27-4.39c0-4.55 3.7-8.26 8.25-8.26 2.2 0 4.27.85 5.83 2.42a8.17 8.17 0 0 1 2.42 5.84c0 4.55-3.7 8.25-8.26 8.25Zm4.53-6.18c-.25-.13-1.49-.74-1.72-.82-.23-.08-.4-.13-.57.12-.17.25-.65.82-.8.99-.15.17-.29.19-.54.06-.25-.13-1.05-.39-2-1.25-.74-.66-1.24-1.48-1.39-1.73-.15-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.15.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.57-1.37-.78-1.88-.21-.49-.42-.42-.57-.43h-.49c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.44 1.02 2.61c.13.17 1.76 2.69 4.26 3.77.59.26 1.05.42 1.41.54.59.19 1.12.16 1.54.1.47-.07 1.49-.61 1.7-1.2.21-.59.21-1.1.15-1.2-.06-.1-.22-.17-.46-.29Z"/>
                </svg>
            SVG,
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

<section class="px-6 pb-6 pt-4 lg:px-8 lg:pb-8">
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

            <div class="surface-panel relative overflow-hidden rounded-[2.4rem] p-5 sm:p-6">
                <div class="prefooter-orb prefooter-orb-bottom" aria-hidden="true"></div>

                <div class="relative z-10 max-w-3xl">
                    <span class="section-label">{{ __('site.footer.community.eyebrow') }}</span>
                    <h2 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.footer.community.title') }}</h2>
                </div>

                <div class="community-icon-grid relative z-10 mt-6 grid grid-cols-2 gap-4">
                    @foreach ($communityLinks as $communityLink)
                        <a
                            href="{{ $communityLink['url'] }}"
                            target="_blank"
                            rel="noreferrer"
                            aria-label="{{ $communityLink['title'] }}"
                            class="community-icon-card community-icon-card--{{ $communityLink['key'] }} group rounded-[1.8rem] p-5 sm:p-6"
                        >
                            <span class="community-card-icon">
                                {!! $communityLink['icon'] !!}
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
