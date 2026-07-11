@php
    $recaptchaEnabled = (bool) config('services.recaptcha.enabled', false);
    $recaptchaSiteKey = trim((string) config('services.recaptcha.site_key', ''));
@endphp

@if ($recaptchaEnabled && $recaptchaSiteKey !== '')
    <div class="overflow-x-auto rounded-2xl border border-white/10 bg-black/15 p-3">
        <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
    </div>
@endif
