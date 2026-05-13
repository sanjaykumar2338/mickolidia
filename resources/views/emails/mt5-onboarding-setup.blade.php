<x-emails.layout
    :status="__('MT5 Connector')"
    :title="__('Install the Updated MT5 Connector')"
    :primary-url="$dashboardUrl"
    :primary-label="__('Open Wolforix Dashboard')"
    :secondary-url="$setupUrl"
    :secondary-label="__('Open MT5 Setup Page')"
    :support-email="$supportEmail"
>
    <x-slot:intro>
        Hello,<br><br>
        We recently released updated Wolforix MT5 Connector improvements ({{ $connectorReleaseLabel }}). Please log in to your Wolforix dashboard, download the latest EA connector files, and reconnect MetaTrader 5 so live synchronization, rule monitoring, and dashboard metrics work correctly.
    </x-slot:intro>

    <x-slot:cards>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <tr>
                <td width="33.33%" style="padding-right:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Account</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">Updated</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">The latest connector files are ready in your dashboard.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding:0 4px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Metrics</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">Reconnect</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Reconnect MT5 to restore live sync and rule checks.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding-left:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">EA</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">Free Account</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">A free funded account opportunity will be provided.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </x-slot:cards>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">
        Required Setup
    </p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        Install the updated Wolforix MT5 Connector EA
    </p>

    <p style="margin:14px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        Some earlier MT5 sync/connectivity issues may have affected proper rule tracking and monitoring. To make sure your Wolforix dashboard reads the correct live data, please replace the previous connector files with the latest package from your dashboard, attach the updated EA to MT5, and allow it to complete a fresh sync.
    </p>

    <p style="margin:14px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        Thank you sincerely for your patience and support while we stabilized the connector. As a goodwill step, Wolforix will provide you with a completely free funded account opportunity so you can continue testing your trading skills with us.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px; border-collapse:collapse;">
        @foreach ([
            'Log in to your Wolforix dashboard.',
            'Open the MT5 setup page.',
            'Watch the MT5 setup tutorial video.',
            'Download the latest preconfigured connector package from the setup page.',
            'Extract the ZIP file on your computer.',
            'Replace any older Wolforix connector files with the updated package files.',
            'Copy WolforixRuleEngineEA.mq5 into MQL5/Experts.',
            'Copy the Include files from the package into MQL5/Include.',
            'Open MetaTrader 5.',
            'Enable Algo Trading.',
            'Go to Tools > Options > Expert Advisors.',
            'Allow WebRequest for https://www.wolforix.com and https://wolforix.com.',
            'Attach the EA to an active chart.',
            'Wait for the dashboard status to show Connected.',
        ] as $index => $step)
            <tr>
                <td style="padding:0 0 12px 0;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; border-bottom:{{ $loop->last ? '0' : '1px solid rgba(255,255,255,0.06)' }};">
                        <tr>
                            <td width="54" valign="top" style="padding:0 0 12px 0;">
                                <span style="display:inline-block; width:38px; height:38px; line-height:38px; border-radius:999px; text-align:center; background:rgba(244,183,74,0.12); border:1px solid rgba(244,183,74,0.24); color:#f8d57c; font-size:12px; font-weight:700;">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            </td>
                            <td valign="top" style="padding:7px 0 12px 0;">
                                <p style="margin:0; color:#d5deea; font-size:14px; line-height:1.8;">{{ $step }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endforeach
    </table>

    <div style="margin:22px 0 0 0; padding:18px; border:1px solid rgba(248,213,124,0.22); border-radius:18px; background-color:rgba(244,183,74,0.08);">
        <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Important</p>
        <p style="margin:12px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
            Please use the updated connector package from your dashboard rather than an older ZIP. Until the updated EA connector is installed, attached to a chart, and successfully synced, live P/L, rule monitoring, trading days, account statistics, and profit updates may not appear correctly.
        </p>
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:22px; border-collapse:separate; border-spacing:0;">
        <tr>
            <td style="padding:0 0 12px 0;">
                <a href="{{ $setupVideoUrl }}" style="display:block; padding:15px 20px; border-radius:18px; border:1px solid rgba(248,213,124,0.30); background:rgba(244,183,74,0.10); color:#f8d57c; font-size:14px; font-weight:700; text-align:center; text-decoration:none;">Watch Setup Video</a>
            </td>
        </tr>
    </table>

    <p style="margin:8px 0 0 0; color:#94a3b8; font-size:12px; line-height:1.8; word-break:break-all;">
        Dashboard: <a href="{{ $dashboardUrl }}" style="color:#f8d57c; font-weight:700; text-decoration:none;">{{ $dashboardUrl }}</a><br>
        Setup page: <a href="{{ $setupUrl }}" style="color:#f8d57c; font-weight:700; text-decoration:none;">{{ $setupUrl }}</a><br>
        Latest connector ZIP: <a href="{{ $connectorDownloadUrl }}" style="color:#f8d57c; font-weight:700; text-decoration:none;">{{ $connectorDownloadUrl }}</a><br>
        Connector release: {{ $connectorReleaseLabel }}<br>
        Setup video: <a href="{{ $setupVideoUrl }}" style="color:#f8d57c; font-weight:700; text-decoration:none;">{{ $setupVideoUrl }}</a>
    </p>

    <p style="margin:18px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        If you need help installing the EA or confirming the connection status, contact
        <a href="mailto:{{ $supportEmail }}" style="color:#f8d57c; font-weight:700; text-decoration:none;">{{ $supportEmail }}</a>.
    </p>

    <p style="margin:18px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
        Best regards,<br>
        Wolforix Team
    </p>
</x-emails.layout>
