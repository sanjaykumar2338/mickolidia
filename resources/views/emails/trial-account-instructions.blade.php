<x-emails.layout
    :status="__('Trial Account')"
    :title="__('Your Wolforix Trial Account')"
    :primary-url="$demoRegistrationUrl"
    :primary-label="__('Open Demo Registration')"
    :secondary-url="route('trial.setup')"
    :secondary-label="__('View Connector Details')"
>
    <x-slot:intro>
        Hello,<br><br>
        Thank you for registering for your Wolforix Trial Account.
    </x-slot:intro>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">Get Started</p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        Connect your MT5 demo account with the Wolforix connector
    </p>

    <p style="margin:18px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        First, create your IC Markets MT5 demo account using the link below:
    </p>

    <p style="margin:14px 0 0 0; color:#f8d57c; font-size:14px; line-height:1.8; word-break:break-all;">
        <a href="{{ $demoRegistrationUrl }}" style="color:#f8d57c; font-weight:700; text-decoration:none;">{{ $demoRegistrationUrl }}</a>
    </p>

    <p style="margin:18px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        Then open your Wolforix trial dashboard, download the MT5 connector, and install the EA inside MetaTrader 5. Connection happens inside MT5, not through a website form.
    </p>

    <ol style="margin:18px 0 0 20px; padding:0; color:#d5deea; font-size:14px; line-height:1.8;">
        <li>Register for Free Trial on Wolforix.</li>
        <li>Create your IC Markets MT5 demo account.</li>
        <li>Download the Wolforix MT5 connector from your trial dashboard.</li>
        <li>Install the EA files in MetaTrader 5.</li>
        <li>Copy the Base URL, Account Reference, and Secret Token from your dashboard.</li>
        <li>Paste them into the EA settings popup inside MT5.</li>
        <li>Click OK. Your dashboard will show Connected after the EA sends its first update.</li>
    </ol>

    <p style="margin:18px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        Connector download:
        <a href="{{ asset('mt5software/wolforix-mt5-connector.zip') }}" style="color:#f8d57c; font-weight:700; text-decoration:none;">{{ asset('mt5software/wolforix-mt5-connector.zip') }}</a>
    </p>

    <p style="margin:18px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        Dashboard connector details:
        <a href="{{ route('trial.setup') }}" style="color:#f8d57c; font-weight:700; text-decoration:none;">{{ route('trial.setup') }}</a>
    </p>

    <p style="margin:18px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        Wolforix does not collect the MT5 account number through a website form. Install the MT5 connector and connect your account using the credentials provided in your dashboard.
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
