<x-emails.layout
    :status="__('Account Active')"
    :title="__('Welcome to Wolforix')"
    :primary-url="route('dashboard')"
    :primary-label="__('Open Your Dashboard')"
    :secondary-url="route('home').'#plans'"
    :secondary-label="__('Review Challenge Plans')"
>
    <x-slot:intro>
        Hi <strong style="color:#ffffff;">{{ $user->name }}</strong>, your account is ready. You now have access to the same dark premium experience used across Wolforix plans, checkout, and your dashboard.
    </x-slot:intro>

    <x-slot:cards>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <tr>
                <td width="33.33%" style="padding-right:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Platform</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">Ready</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Your profile and access flow are active.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding:0 4px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Models</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">2 Paths</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Explore 1-Step Instant and 2-Step Pro.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding-left:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Support</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">On Standby</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Need help? Our team is one message away.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </x-slot:cards>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">Start Here</p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        Your next three moves
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px; border-collapse:collapse;">
        <tr>
            <td style="padding:0 0 14px 0;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; border-bottom:1px solid rgba(255,255,255,0.06);">
                    <tr>
                        <td width="58" valign="top" style="padding:0 0 14px 0;">
                            <span style="display:inline-block; width:40px; height:40px; line-height:40px; border-radius:999px; text-align:center; background:rgba(244,183,74,0.12); border:1px solid rgba(244,183,74,0.24); color:#f8d57c; font-size:13px; font-weight:700;">01</span>
                        </td>
                        <td valign="top" style="padding:0 0 14px 0;">
                            <p style="margin:0; color:#ffffff; font-size:16px; font-weight:700;">Preview the platform</p>
                            <p style="margin:8px 0 0 0; color:#94a3b8; font-size:14px; line-height:1.8;">Review the dashboard layout, challenge flow, and payout-related panels before you place an order.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding:0 0 14px 0;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; border-bottom:1px solid rgba(255,255,255,0.06);">
                    <tr>
                        <td width="58" valign="top" style="padding:0 0 14px 0;">
                            <span style="display:inline-block; width:40px; height:40px; line-height:40px; border-radius:999px; text-align:center; background:rgba(244,183,74,0.12); border:1px solid rgba(244,183,74,0.24); color:#f8d57c; font-size:13px; font-weight:700;">02</span>
                        </td>
                        <td valign="top" style="padding:0 0 14px 0;">
                            <p style="margin:0; color:#ffffff; font-size:16px; font-weight:700;">Compare funding models</p>
                            <p style="margin:8px 0 0 0; color:#94a3b8; font-size:14px; line-height:1.8;">Choose between the direct structure of 1-Step Instant and the scale-focused route of 2-Step Pro.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding:0 0 4px 0;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                    <tr>
                        <td width="58" valign="top">
                            <span style="display:inline-block; width:40px; height:40px; line-height:40px; border-radius:999px; text-align:center; background:rgba(244,183,74,0.12); border:1px solid rgba(244,183,74,0.24); color:#f8d57c; font-size:13px; font-weight:700;">03</span>
                        </td>
                        <td valign="top">
                            <p style="margin:0; color:#ffffff; font-size:16px; font-weight:700;">Move when you're ready</p>
                            <p style="margin:8px 0 0 0; color:#94a3b8; font-size:14px; line-height:1.8;">Continue to checkout at any time. Your account stays ready, and support is available if you need guidance.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</x-emails.layout>
