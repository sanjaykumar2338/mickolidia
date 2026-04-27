<x-emails.layout
    :status="__('Support Alert')"
    :title="__('New Wolforix challenge purchase')"
    :primary-url="$user ? route('admin.clients.show', $user) : route('admin.clients.index')"
    :primary-label="__('Open Client Record')"
    :secondary-url="route('dashboard')"
    :secondary-label="__('Dashboard')"
>
    <x-slot:intro>
        A client has purchased a Wolforix plan and an account record has been provisioned. Review MT5 account stock and operations status below.
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
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Assigned MT5</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:18px; font-weight:700;">{{ $details['mt5_login'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">{{ $details['mt5_server'] }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding-left:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Remaining Stock</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:18px; font-weight:700;">{{ $details['remaining_same_size'] }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">{{ $details['remaining_total'] }} total FusionMarkets accounts</p>
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
            ['label' => 'Purchased plan', 'value' => (string) $details['purchased_plan']],
            ['label' => 'Account size', 'value' => (string) $details['account_size']],
            ['label' => 'Order number', 'value' => (string) $details['order_number']],
            ['label' => 'Payment reference', 'value' => (string) $details['payment_reference']],
            ['label' => 'MT5 login', 'value' => (string) $details['mt5_login']],
            ['label' => 'Broker/provider', 'value' => (string) $details['broker']],
            ['label' => 'MT5 server', 'value' => (string) $details['mt5_server']],
            ['label' => 'Account reference', 'value' => (string) $details['account_reference']],
            ['label' => 'Purchased at', 'value' => (string) $details['purchased_at']],
            ['label' => 'Remaining same-size accounts', 'value' => (string) $details['remaining_same_size']],
            ['label' => 'Remaining FusionMarkets accounts', 'value' => (string) $details['remaining_total']],
        ],
    ])

    <p style="margin:0; color:#d5deea; font-size:14px; line-height:1.8;">
        This support notification intentionally excludes trading and investor passwords. Customer credential delivery is handled by the customer-facing account details email.
    </p>
</x-emails.layout>
