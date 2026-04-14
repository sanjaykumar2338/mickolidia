<x-emails.layout
    :status="__('Evaluation Passed')"
    :title="__('Congratulations — You’ve Passed the Evaluation')"
    :primary-url="route('dashboard')"
    :primary-label="__('Open Your Dashboard')"
    :secondary-url="route('dashboard.payouts')"
    :secondary-label="__('Review Payouts')"
>
    <x-slot:intro>
        Hi <strong style="color:#ffffff;">{{ $user->name }}</strong>, congratulations. You have successfully passed the Wolforix evaluation and reached funded account review status.
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
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">This evaluation is now in the final review lane.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding:0 4px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Plan</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $details['plan'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Your performance has cleared the evaluation target.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding-left:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Profit Target</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $details['profit_target'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">{{ $details['profit_target_percent'] }} reached.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </x-slot:cards>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">Funded Review</p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        You are through the evaluation
    </p>

    @include('emails.partials.details-table', [
        'rows' => [
            ['label' => 'Account ID', 'value' => (string) $tradingAccount->account_reference],
            ['label' => 'Plan', 'value' => (string) $details['plan']],
            ['label' => 'Status', 'value' => 'Funded account review'],
            ['label' => 'Profit Target', 'value' => (string) $details['profit_target'].' ('.(string) $details['profit_target_percent'].')'],
        ],
    ])

    <p style="margin:0 0 12px 0; color:#d5deea; font-size:14px; line-height:1.8;">
        Our team will complete the funded account review and follow up with the next step. Please keep an eye on your email for any additional instructions.
    </p>

    @if (! empty($certificate))
        <p style="margin:0; color:#d5deea; font-size:14px; line-height:1.8;">
            Your personalized Wolforix funded trader certificate is attached to this email.
        </p>
    @endif
</x-emails.layout>
