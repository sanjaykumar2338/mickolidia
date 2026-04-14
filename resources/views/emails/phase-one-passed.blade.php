<x-emails.layout
    :status="__('Phase 1 Passed')"
    :title="__('You’ve Passed Phase 1')"
    :primary-url="route('dashboard')"
    :primary-label="__('Open Your Dashboard')"
    :secondary-url="'mailto:'.config('wolforix.support.email')"
    :secondary-label="__('Contact Support')"
>
    <x-slot:intro>
        Hi <strong style="color:#ffffff;">{{ $traderName }}</strong>, congratulations. You have successfully passed Phase 1 of the Wolforix 2-Step Pro evaluation.
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
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Your Phase 1 objectives are complete.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding:0 4px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Completed Phase</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $details['completed_phase'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Your results have been recorded successfully.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding-left:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Next Step</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $details['next_phase'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Phase 2 details will follow as soon as they are available.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </x-slot:cards>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">Phase Progress</p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        Phase 2 is the next stop
    </p>

    @include('emails.partials.details-table', [
        'rows' => [
            ['label' => 'Account ID', 'value' => (string) $tradingAccount->account_reference],
            ['label' => 'Challenge', 'value' => (string) $details['plan']],
            ['label' => 'Completed Phase', 'value' => (string) $details['completed_phase']],
            ['label' => 'Next Step', 'value' => (string) $details['next_phase']],
        ],
    ])

    <p style="margin:0; color:#d5deea; font-size:14px; line-height:1.8;">
        Your Phase 2 account details will be sent automatically once the Phase 2 account is available.
    </p>
</x-emails.layout>
