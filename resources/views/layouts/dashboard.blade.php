<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dashboard-scrollbar-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('site.meta.default_title'))</title>
    <meta name="description" content="{{ __('site.meta.description') }}">
    <link rel="icon" type="image/png" href="{{ asset('newfolder/IMG_8542.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('newfolder/IMG_8542.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="overflow-x-hidden selection:bg-amber-400/30 selection:text-white"
    data-launch-promo-code="{{ session('launch_offer.applied') ? config('wolforix.launch_discount.code') : '' }}"
>
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute inset-0 grid-pattern opacity-25"></div>
        <div class="absolute left-[12%] top-[8rem] h-[22rem] w-[22rem] rounded-full bg-sky-500/10 blur-3xl"></div>
        <div class="absolute right-[-4rem] top-[-6rem] h-[20rem] w-[20rem] rounded-full bg-amber-400/8 blur-3xl"></div>
    </div>

    <div class="min-h-screen overflow-x-hidden lg:grid lg:grid-cols-[300px_minmax(0,1fr)]">
        @include('partials.dashboard-sidebar')

        <div class="relative min-w-0 overflow-x-hidden">
            @include('partials.dashboard-topbar')

            <main class="min-w-0 px-4 pb-12 pt-4 sm:px-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>

    @include('partials.wolfi-modal')
</body>
</html>
