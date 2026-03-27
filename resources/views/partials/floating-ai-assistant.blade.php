@php
    $assistantHref = request()->routeIs('contact')
        ? '#voice-assistant'
        : route('contact').'#voice-assistant';
@endphp

<a
    href="{{ $assistantHref }}"
    class="assistant-fab primary-cta rounded-full px-5 py-3 text-sm font-semibold"
    aria-label="{{ __('site.ai_assistant.floating_aria') }}"
>
    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-slate-950/18 text-current">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10.5h8M8 14h5m-7.5 5.25V6.75A2.25 2.25 0 0 1 7.75 4.5h8.5a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25H11l-4.5 2.75Z" />
        </svg>
    </span>
    <span>{{ __('site.ai_assistant.floating_label') }}</span>
</a>
