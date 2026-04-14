<x-emails.layout
    :status="__('Challenge Failed')"
    :title="__('Account Status Update')"
    :primary-url="route('dashboard')"
    :primary-label="__('Open Your Dashboard')"
    :secondary-url="'mailto:'.$details['support_email']"
    :secondary-label="__('Contact Support')"
>
    <x-slot:intro>
        Hi <strong style="color:#ffffff;">{{ $user->name }}</strong>, we’re sorry to let you know that your Wolforix challenge account has breached one of the required risk rules.
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
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">This evaluation account is now locked.</p>
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
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">The evaluation stopped after this rule was triggered.</p>
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
        Challenge status has been closed
    </p>

    @include('emails.partials.details-table', [
        'rows' => [
            ['label' => 'Account ID', 'value' => (string) $tradingAccount->account_reference],
            ['label' => 'Plan', 'value' => (string) $details['plan']],
            ['label' => 'Rule Breached', 'value' => (string) $details['rule']],
            ['label' => 'Threshold', 'value' => (string) $details['threshold']],
            ['label' => 'Recorded Value', 'value' => (string) $details['recorded_value']],
        ],
    ])

    <p style="margin:0 0 12px 0; color:#d5deea; font-size:14px; line-height:1.8;">
        This challenge account has now been permanently disabled and invalidated. Trading access for this challenge is blocked to protect the integrity of the evaluation.
    </p>
    <p style="margin:0; color:#d5deea; font-size:14px; line-height:1.8;">
        If you believe this requires review or you need help understanding the rule breach, please contact our support team.
    </p>
</x-emails.layout>
