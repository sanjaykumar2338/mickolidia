<x-emails.layout
    :status="__('Trial Account')"
    :title="__('Your Wolforix Trial Account')"
    :primary-url="$demoRegistrationUrl"
    :primary-label="__('Open Demo Registration')"
    :secondary-url="route('trial.setup')"
    :secondary-label="__('View Connector Details')"
>
    @php
        $setupVideoUrl = 'https://www.wolforix.com/mt5_demo.mp4';
    @endphp

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
        Then log in to your Wolforix dashboard, open the trial setup page, watch the setup video, download your preconfigured MT5 connector package, and install the EA inside MetaTrader 5. Connection happens inside MT5, not through a website form.
    </p>

    <div style="margin:22px 0 0 0; padding:18px; border:1px solid rgba(248,213,124,0.22); border-radius:18px; background-color:rgba(244,183,74,0.08);">
        <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">MT5 Setup Tutorial</p>
        <p style="margin:12px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
            Watch the setup video before connecting your account. It explains how to install the Wolforix MT5 Connector and connect MetaTrader 5 to your Wolforix dashboard.
        </p>
        <p style="margin:16px 0 0 0;">
            <a href="{{ $setupVideoUrl }}" style="display:inline-block; border-radius:999px; background-color:#f4b74a; color:#06111f; font-size:14px; font-weight:800; line-height:1; padding:14px 22px; text-decoration:none;">Watch Setup Video</a>
        </p>
        <p style="margin:12px 0 0 0; color:#94a3b8; font-size:12px; line-height:1.7; word-break:break-all;">
            <a href="{{ $setupVideoUrl }}" style="color:#f8d57c; font-weight:700; text-decoration:none;">{{ $setupVideoUrl }}</a>
        </p>
    </div>

    <ol style="margin:18px 0 0 20px; padding:0; color:#d5deea; font-size:14px; line-height:1.8;">
        <li>Register for Free Trial on Wolforix.</li>
        <li>Create your IC Markets MT5 demo account.</li>
        <li>Log in to your Wolforix dashboard and open the trial setup page.</li>
        <li>Watch the MT5 setup tutorial video before connecting your account.</li>
        <li>Download the preconfigured Wolforix MT5 connector package from your trial dashboard.</li>
        <li>In MetaTrader 5, click File &gt; Open Data Folder. When File Explorer opens, go to MQL5 &gt; Experts and paste the WolforixRuleEngineEA.mq5 file or extracted connector folder there.</li>
        <li>Copy the Include files from the connector package into MQL5 &gt; Include.</li>
        <li>Open Tools &gt; Options &gt; Expert Advisors, tick Allow WebRequest for listed URL, then add https://www.wolforix.com and https://wolforix.com.</li>
        <li>Copy the Base URL, Account Reference, and Secret Token from your dashboard.</li>
        <li>Attach the EA to a chart, paste the values into the EA settings popup inside MT5, then click OK.</li>
        <li>Wait for your dashboard status to become Connected after the EA sends its first update.</li>
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
