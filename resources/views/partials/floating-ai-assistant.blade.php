@php
    $wolfiFabImage = asset((string) config('wolfi.images.shortcut'));
@endphp

<button
    type="button"
    data-wolfi-launch
    class="assistant-fab assistant-fab-button"
    aria-label="{{ __('site.ai_assistant.floating_aria') }}"
    aria-controls="wolfi-modal"
    aria-expanded="false"
>
    <span class="assistant-fab-shimmer" aria-hidden="true"></span>
    <span class="assistant-fab-avatar-wrap" aria-hidden="true">
        <span class="assistant-fab-halo"></span>
        <span class="assistant-fab-orbit assistant-fab-orbit-left"></span>
        <span class="assistant-fab-orbit assistant-fab-orbit-right"></span>
        <span class="assistant-fab-core">
            <img
                src="{{ $wolfiFabImage }}"
                alt=""
                class="assistant-fab-avatar"
                loading="eager"
                decoding="async"
            >
            <video
                data-wolfi-fab-video
                class="assistant-fab-avatar-video"
                muted
                loop
                playsinline
                preload="metadata"
                poster="{{ $wolfiFabImage }}"
                aria-hidden="true"
                disablepictureinpicture
            >
                <source src="{{ asset('2136dfb8-85de-461a-9b2b-0d60c39ad04e.mp4') }}" type="video/mp4">
            </video>
        </span>
        <span class="assistant-fab-ping"></span>
    </span>
</button>

@include('partials.wolfi-modal')
