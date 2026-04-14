@props([
    'status' => 'Account Update',
    'title' => 'Wolforix Update',
    'badge' => 'Trade Fearlessly. Win Real.',
    'primaryUrl' => null,
    'primaryLabel' => null,
    'secondaryUrl' => null,
    'secondaryLabel' => null,
    'supportEmail' => config('wolforix.support.email'),
    'footerNote' => 'This message was designed to mirror the Wolforix premium dark brand across the website, checkout, and onboarding flow.',
])

<div style="margin:0; padding:34px 0; background-color:#050b13; background-image:radial-gradient(circle at top left, rgba(33,69,121,0.28), transparent 34%), radial-gradient(circle at top right, rgba(244,183,74,0.10), transparent 24%), linear-gradient(180deg, #03060d 0%, #07101a 56%, #091320 100%); font-family:Arial, Helvetica, sans-serif; color:#f8fafc;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
        <tr>
            <td align="center" style="padding:0 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:660px; border-collapse:collapse;">
                    <tr>
                        <td style="padding:0 0 18px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="color:#f4b74a; font-size:13px; font-weight:700; letter-spacing:0.32em; text-transform:uppercase;">
                                        Wolforix
                                    </td>
                                    <td align="right" style="color:#94a3b8; font-size:12px; letter-spacing:0.16em; text-transform:uppercase;">
                                        {{ $status }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="border:1px solid rgba(148,163,184,0.14); border-radius:34px; overflow:hidden; background:linear-gradient(180deg, rgba(12,18,30,0.98) 0%, rgba(6,10,19,0.96) 100%); box-shadow:0 34px 90px rgba(2,6,23,0.48);">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="padding:0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; background:radial-gradient(circle at top right, rgba(244,183,74,0.14), transparent 28%), radial-gradient(circle at bottom left, rgba(43,92,166,0.22), transparent 36%), linear-gradient(180deg, rgba(9,14,24,0.99) 0%, rgba(5,9,17,0.97) 100%);">
                                            <tr>
                                                <td style="padding:42px 38px 18px 38px;">
                                                    <span style="display:inline-block; padding:10px 16px; border:1px solid rgba(244,183,74,0.26); border-radius:999px; background:rgba(244,183,74,0.10); color:#f8d57c; font-size:12px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">
                                                        {{ $badge }}
                                                    </span>
                                                    <h1 style="margin:24px 0 0 0; color:#ffffff; font-size:40px; line-height:1.05; font-weight:700;">
                                                        {{ $title }}
                                                    </h1>
                                                    @if (isset($intro) && trim((string) $intro) !== '')
                                                        <div style="margin:18px 0 0 0; color:#d5deea; font-size:17px; line-height:1.8;">
                                                            {{ $intro }}
                                                        </div>
                                                    @endif

                                                    @if (isset($cards) && trim((string) $cards) !== '')
                                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:28px; border-collapse:separate; border-spacing:0;">
                                                            <tr>
                                                                <td style="padding:0 0 14px 0;">
                                                                    {{ $cards }}
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    @endif

                                                    @if (($primaryUrl && $primaryLabel) || ($secondaryUrl && $secondaryLabel))
                                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:6px; border-collapse:separate; border-spacing:0;">
                                                            <tr>
                                                                @if ($primaryUrl && $primaryLabel)
                                                                    <td style="padding-right:{{ ($secondaryUrl && $secondaryLabel) ? '8px' : '0' }};">
                                                                        <a href="{{ $primaryUrl }}" style="display:block; padding:16px 20px; border-radius:18px; background:linear-gradient(135deg, #f4b74a 0%, #f8d57c 100%); color:#06101c; font-size:14px; font-weight:700; text-align:center; text-decoration:none; box-shadow:0 18px 40px rgba(244,183,74,0.22);">
                                                                            {{ $primaryLabel }}
                                                                        </a>
                                                                    </td>
                                                                @endif
                                                                @if ($secondaryUrl && $secondaryLabel)
                                                                    <td style="padding-left:{{ ($primaryUrl && $primaryLabel) ? '8px' : '0' }};">
                                                                        <a href="{{ $secondaryUrl }}" style="display:block; padding:16px 20px; border-radius:18px; border:1px solid rgba(255,255,255,0.10); background:rgba(255,255,255,0.03); color:#f8fafc; font-size:14px; font-weight:700; text-align:center; text-decoration:none;">
                                                                            {{ $secondaryLabel }}
                                                                        </a>
                                                                    </td>
                                                                @endif
                                                            </tr>
                                                        </table>
                                                    @endif
                                                </td>
                                            </tr>
                                            @if (trim((string) $slot) !== '')
                                                <tr>
                                                    <td style="padding:0 38px 34px 38px;">
                                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; border:1px solid rgba(255,255,255,0.06); border-radius:28px; background:linear-gradient(180deg, rgba(10,17,29,0.96) 0%, rgba(7,12,22,0.9) 100%);">
                                                            <tr>
                                                                <td style="padding:26px 26px 20px 26px;">
                                                                    {{ $slot }}
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0 38px 36px 38px; background:rgba(6,11,20,0.92); border-top:1px solid rgba(255,255,255,0.06);">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:24px; border-collapse:collapse;">
                                            <tr>
                                                <td style="padding:18px 20px; border:1px solid rgba(255,255,255,0.06); border-radius:22px; background:rgba(255,255,255,0.02);">
                                                    <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Support</p>
                                                    <p style="margin:10px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
                                                        Reach the team at
                                                        <a href="mailto:{{ $supportEmail }}" style="color:#f8d57c; font-weight:700; text-decoration:none;">{{ $supportEmail }}</a>.
                                                        Outside business hours, replies may take a little longer.
                                                    </p>
                                                    @if (isset($footer) && trim((string) $footer) !== '')
                                                        <div style="margin:14px 0 0 0; color:#d5deea; font-size:14px; line-height:1.8;">
                                                            {{ $footer }}
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                        @if (filled($footerNote))
                                            <p style="margin:18px 0 0 0; color:#64748b; font-size:12px; line-height:1.8; text-align:center;">
                                                {{ $footerNote }}
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
