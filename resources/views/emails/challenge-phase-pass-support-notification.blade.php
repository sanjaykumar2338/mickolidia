<x-emails.layout
    :status="__('Support Alert')"
    :title="__('Challenge phase passed')"
    :primary-url="route('admin.clients.show', $user)"
    :primary-label="__('Open Client Record')"
    :secondary-url="route('dashboard')"
    :secondary-label="__('Dashboard')"
>
    <x-slot:intro>
        A Wolforix account has passed a challenge phase and requires support review for the next operational step.
    </x-slot:intro>

    <x-slot:cards>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <tr>
                <td width="33.33%" style="padding-right:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Client</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:18px; font-weight:700;">{{ $details['client_name'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">{{ $details['client_email'] }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding:0 4px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Account</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:18px; font-weight:700;">{{ $details['account_reference'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">ID {{ $details['account_id'] }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding-left:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Completed Phase</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:18px; font-weight:700;">{{ $details['completed_phase'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">{{ $details['current_status'] }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </x-slot:cards>

    @include('emails.partials.details-table', [
        'rows' => [
            ['label' => 'Client name', 'value' => (string) $details['client_name']],
            ['label' => 'Client email', 'value' => (string) $details['client_email']],
            ['label' => 'Account reference', 'value' => (string) $details['account_reference']],
            ['label' => 'Account ID', 'value' => (string) $details['account_id']],
            ['label' => 'Challenge type', 'value' => (string) $details['challenge_type']],
            ['label' => 'Completed phase', 'value' => (string) $details['completed_phase']],
            ['label' => 'Current status', 'value' => (string) $details['current_status']],
            ['label' => 'Pass timestamp', 'value' => (string) $details['pass_timestamp']],
            ['label' => 'MT5 login', 'value' => (string) $details['mt5_login']],
            ['label' => 'MT5 disable status', 'value' => (string) $details['mt5_deactivation_status']],
        ],
    ])

    <p style="margin:0; color:#d5deea; font-size:14px; line-height:1.8;">
        MT5 trading access has been marked for automatic disablement. Confirm the next account/provisioning step before the client resumes trading.
    </p>
</x-emails.layout>
