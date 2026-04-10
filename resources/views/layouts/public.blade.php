<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('site.meta.default_title'))</title>
    <meta name="description" content="@yield('description', __('site.meta.description'))">
    <link rel="icon" type="image/png" href="{{ asset('newfolder/IMG_8542.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('newfolder/IMG_8542.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $hasPostMainSections = ! request()->routeIs('checkout.*', 'login', 'password.*');
@endphp
<body
    class="selection:bg-amber-400/30 selection:text-white"
    data-launch-promo-code="{{ session('launch_offer.applied') ? config('wolforix.launch_discount.code') : '' }}"
>
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute inset-0 grid-pattern opacity-40"></div>
        <div class="absolute left-[8%] top-[-12rem] h-[28rem] w-[28rem] rounded-full bg-amber-400/8 blur-3xl"></div>
        <div class="absolute right-[6%] top-[8rem] h-[24rem] w-[24rem] rounded-full bg-sky-500/8 blur-3xl"></div>
    </div>

    @include('partials.public-nav')
    @include('partials.site-search')
    @if (request()->routeIs('home') && ! session()->has('launch_offer.decision'))
        @include('partials.launch-popup')
    @endif
    @include('partials.cookie-consent-banner')

    @if (session('checkout_success') || session('trial_success'))
        <div class="relative z-30 mx-auto mt-5 max-w-7xl px-6 lg:px-8">
            <div data-flash class="flash-transition rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                {{ session('checkout_success') ?? session('trial_success') }}
            </div>
        </div>
    @endif

    <main class="relative z-0 {{ $hasPostMainSections ? 'pb-0' : 'pb-32 md:pb-28' }}">
        @yield('content')
    </main>

    @unless (request()->routeIs('contact'))
        @include('partials.ai-assistant-promo')
    @endunless

    @unless (request()->routeIs('checkout.*', 'login', 'password.*'))
        @include('partials.public-payment-community')
    @endunless

    @include('partials.public-footer')
    @include('partials.back-to-top')
    @include('partials.floating-ai-assistant')
    @include('partials.fixed-disclaimer')
</body>
</html>
