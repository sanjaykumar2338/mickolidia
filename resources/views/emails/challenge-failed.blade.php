<x-emails.layout
    :status="__('Challenge Failed')"
    :title="__('Account Status Update')"
    :primary-url="route('dashboard')"
    :primary-label="__('Open Your Dashboard')"
    :secondary-url="'mailto:'.$details['support_email']"
    :secondary-label="__('Contact Support')"
>
    <x-slot:intro>
        Hi <strong style="color:#ffffff;">{{ $user->name }}</strong>, we’re sorry to let you know that your Wolforix challenge account has violated one of the required trading rules.
    </x-slot:intro>

    <x-slot:cards>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <tr>
                <td width="33.33%" style="padding-right:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Account ID</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $tradingAccount->account_reference }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">This evaluation account is now protected in its final state.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding:0 4px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Rule Breached</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $details['rule'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">The challenge cannot continue in its current phase.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding-left:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Recorded Value</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $details['recorded_value'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Threshold: {{ $details['threshold'] }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </x-slot:cards>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">Evaluation Review</p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        Challenge cannot continue in this phase
    </p>

    @include('emails.partials.details-table', [
        'rows' => [
            ['label' => 'Client Name', 'value' => (string) ($details['client_name'] ?? $user->name)],
            ['label' => 'Client Email', 'value' => (string) ($details['client_email'] ?? $user->email)],
            ['label' => 'Account ID', 'value' => (string) ($details['account_reference'] ?? $tradingAccount->account_reference)],
            ['label' => 'MT5 Login', 'value' => (string) ($details['mt5_login'] ?? ($tradingAccount->platform_login ?: $tradingAccount->platform_account_id ?: 'Not available'))],
            ['label' => 'Plan', 'value' => (string) $details['plan']],
            ['label' => 'Rule Breached', 'value' => (string) $details['rule']],
            ['label' => 'Reason', 'value' => (string) ($details['reason'] ?? $details['rule'])],
            ['label' => 'Violation Timestamp', 'value' => (string) ($details['violation_timestamp'] ?? optional($tradingAccount->failed_at)->toDateTimeString() ?? 'Not available')],
            ['label' => 'Final Account Status', 'value' => (string) ($details['final_account_status'] ?? str((string) ($tradingAccount->challenge_status ?: $tradingAccount->account_status ?: 'failed'))->replace('_', ' ')->title())],
            ['label' => 'Threshold', 'value' => (string) $details['threshold']],
            ['label' => 'Recorded Value', 'value' => (string) $details['recorded_value']],
        ],
    ])

    <p style="margin:0 0 12px 0; color:#d5deea; font-size:14px; line-height:1.8;">
        This challenge cannot continue in its current phase because the rule above was triggered. Trading access is blocked for this account to protect the integrity of the evaluation.
    </p>
    <p style="margin:0; color:#d5deea; font-size:14px; line-height:1.8;">
        Please treat this as useful feedback rather than a setback. Reviewing the decision sequence, risk sizing, and timing can help you return stronger in future participation. Our support team is here if you need help understanding the rule.
    </p>
</x-emails.layout>
