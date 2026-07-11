@php
    $recaptchaEnabled = (bool) config('services.recaptcha.enabled', false);
    $recaptchaSiteKey = trim((string) config('services.recaptcha.site_key', ''));
    $recaptchaType = strtolower(trim((string) config('services.recaptcha.type', 'v3'))) ?: 'v3';
    $recaptchaAction = (string) ($action ?? 'form_submit');
@endphp

@if ($recaptchaEnabled && $recaptchaSiteKey !== '')
    @if ($recaptchaType === 'checkbox')
        <div class="overflow-x-auto rounded-2xl border border-white/10 bg-black/15 p-3">
            <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
        </div>
    @else
        <input
            type="hidden"
            name="g-recaptcha-response"
            value=""
            data-recaptcha-token
            data-recaptcha-action="{{ $recaptchaAction }}"
        >
    @endif
@endif
