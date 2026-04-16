<x-emails.layout
    :status="$details['status'] === 'breach' ? __('Consistency Threshold Reached') : __('Consistency Threshold Warning')"
    :title="__('Consistency Rule Alert')"
    :primary-url="route('dashboard')"
    :primary-label="__('Open Your Dashboard')"
    :secondary-url="'mailto:'.$details['support_email']"
    :secondary-label="__('Contact Support')"
>
    <x-slot:intro>
        Hi <strong style="color:#ffffff;">{{ $traderName }}</strong>, your Wolforix account has triggered a consistency-rule alert for this month’s realized profits.
    </x-slot:intro>

    <x-slot:cards>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <tr>
                <td width="33.33%" style="padding-right:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Account ID</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $details['account_reference'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">{{ $details['rule_label'] }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding:0 4px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Month Profit</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $details['current_month_profit'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Highest day: {{ $details['highest_single_day_profit'] }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding-left:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Profit Ratio</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $details['ratio_percent'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Threshold: {{ $details['threshold_percent'] }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </x-slot:cards>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">Trading-Day Distribution</p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        Keep monthly profit spread across multiple trading days
    </p>

    @include('emails.partials.details-table', [
        'rows' => [
            ['label' => 'Trader', 'value' => (string) $traderName],
            ['label' => 'Account reference', 'value' => (string) $details['account_reference']],
            ['label' => 'Current month profit', 'value' => (string) $details['current_month_profit']],
            ['label' => 'Highest single-day profit', 'value' => (string) $details['highest_single_day_profit']],
            ['label' => 'Highest single-day date', 'value' => (string) $details['highest_single_day_date']],
            ['label' => 'Computed percentage', 'value' => (string) $details['ratio_percent']],
            ['label' => 'Triggered threshold', 'value' => (string) $details['threshold_percent']],
        ],
    ])

    <p style="margin:0 0 12px 0; color:#d5deea; font-size:14px; line-height:1.8;">
        {{ $details['rule_explanation'] }}
    </p>
    <p style="margin:0; color:#d5deea; font-size:14px; line-height:1.8;">
        Review your dashboard for the latest month profit, highest single-day result, and concentration percentage.
    </p>
</x-emails.layout>
