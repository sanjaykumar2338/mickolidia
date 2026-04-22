@props([
    'class' => '',
    'question' => null,
])

@php
    $talkWolfiImage = asset((string) config('wolfi.images.talk'));
@endphp

<button
    type="button"
    data-wolfi-launch
    @if (filled($question))
        data-wolfi-question="{{ $question }}"
    @endif
    aria-controls="wolfi-modal"
    aria-expanded="false"
    class="talk-with-wolfi-button {{ $class }}"
>
    <span class="talk-with-wolfi-button__avatar-shell" aria-hidden="true">
        <span class="talk-with-wolfi-button__avatar-glow"></span>
        <span class="talk-with-wolfi-button__avatar-ring"></span>
        <span class="talk-with-wolfi-button__avatar">
            <img
                src="{{ $talkWolfiImage }}"
                alt=""
                class="talk-with-wolfi-button__avatar-image"
                loading="lazy"
                decoding="async"
            >
        </span>
    </span>

    <span class="talk-with-wolfi-button__copy">
        <span class="talk-with-wolfi-button__title">Talk with Wolfi</span>
        <span class="talk-with-wolfi-button__subtitle">Get instant guidance now</span>
    </span>

    <span class="talk-with-wolfi-button__arrow" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
        </svg>
    </span>
</button>
