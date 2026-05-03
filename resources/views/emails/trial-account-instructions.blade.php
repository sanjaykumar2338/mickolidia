<x-emails.layout
    :status="__('Trial Account')"
    :title="__('Your Wolforix Trial Account')"
    :primary-url="$demoRegistrationUrl"
    :primary-label="__('Open Demo Registration')"
    :secondary-url="route('trial.setup')"
    :secondary-label="__('Continue Trial Setup')"
>
    <x-slot:intro>
        Hello,<br><br>
        Thank you for registering for your Wolforix Trial Account.
    </x-slot:intro>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">Get Started</p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        Complete your demo account registration
    </p>

    <p style="margin:18px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        To access your demo trading account, please complete your registration using the link below:
    </p>

    <p style="margin:14px 0 0 0; color:#f8d57c; font-size:14px; line-height:1.8; word-break:break-all;">
        <a href="{{ $demoRegistrationUrl }}" style="color:#f8d57c; font-weight:700; text-decoration:none;">{{ $demoRegistrationUrl }}</a>
    </p>

    <p style="margin:18px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        Once you have submitted your details, you will receive your login credentials via email. With these credentials, you can access your demo account and start your free trial.
    </p>

    <p style="margin:18px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        If you need any assistance, feel free to contact us at
        <a href="mailto:{{ config('wolforix.support.email') }}" style="color:#f8d57c; font-weight:700; text-decoration:none;">{{ config('wolforix.support.email') }}</a>.
    </p>

    <p style="margin:18px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        Best regards,<br>
        Wolforix Team
    </p>
</x-emails.layout>
