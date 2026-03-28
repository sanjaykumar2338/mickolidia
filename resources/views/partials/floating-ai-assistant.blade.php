@php
    $assistantHref = request()->routeIs('contact')
        ? '#voice-assistant'
        : route('contact').'#voice-assistant';
@endphp

<a
    href="{{ $assistantHref }}"
    class="assistant-fab assistant-fab-button"
    aria-label="{{ __('site.ai_assistant.floating_aria') }}"
>
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10.5h8M8 14h5m-7.5 5.25V6.75A2.25 2.25 0 0 1 7.75 4.5h8.5a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25H11l-4.5 2.75Z" />
    </svg>
    <span class="sr-only">{{ __('site.ai_assistant.floating_label') }}</span>
</a>
