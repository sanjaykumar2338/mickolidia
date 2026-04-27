<x-emails.layout
    :status="__('Purchase Confirmed')"
    :title="__('Wolforix Challenge Purchase Confirmed')"
    :primary-url="route('dashboard')"
    :primary-label="__('Open Your Dashboard')"
    :secondary-url="route('home').'#plans'"
    :secondary-label="__('Review Challenge Plans')"
>
    <x-slot:intro>
        Hi <strong style="color:#ffffff;">{{ $order->full_name ?: \Illuminate\Support\Str::before((string) $order->email, '@') }}</strong>, your order <strong style="color:#ffffff;">{{ $order->order_number }}</strong> has been confirmed and the provisioning flow is now in motion.
    </x-slot:intro>

    <x-slot:cards>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <tr>
                <td width="33.33%" style="padding-right:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Plan</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ config('wolforix.challenge_catalog.'.$order->challenge_type.'.label', $order->challenge_type === 'one_step' ? '1-Step Instant' : '2-Step Pro') }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">{{ (int) ($order->account_size / 1000) }}K account size selected.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding:0 4px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Amount Paid</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ strtoupper((string) $order->currency) }} {{ number_format((float) $order->final_price, 2) }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Payment completed successfully.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding-left:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Provider</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ ucfirst((string) $order->payment_provider) }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">We will keep your dashboard updated as the account is prepared.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </x-slot:cards>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">Order Summary</p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        Your purchase is locked in
    </p>

    @include('emails.partials.details-table', [
        'rows' => [
            ['label' => 'Order Number', 'value' => (string) $order->order_number],
            ['label' => 'Plan', 'value' => config('wolforix.challenge_catalog.'.$order->challenge_type.'.label', $order->challenge_type === 'one_step' ? '1-Step Instant' : '2-Step Pro')],
            ['label' => 'Account Size', 'value' => (int) ($order->account_size / 1000).'K'],
            ['label' => 'Amount Paid', 'value' => strtoupper((string) $order->currency).' '.number_format((float) $order->final_price, 2)],
            ['label' => 'Payment Provider', 'value' => ucfirst((string) $order->payment_provider)],
        ],
    ])

    @if (is_array($accountAccessDetails) && $accountAccessDetails !== [])
        <div style="height:22px; line-height:22px;">&nbsp;</div>

        <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">Account Access Details</p>
        <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
            {{ $credentialsReady ? 'Your login details are included below' : 'Your account record is created and the access section is ready below' }}
        </p>

        @include('emails.partials.details-table', [
            'rows' => array_values(array_filter([
                ['label' => 'Platform', 'value' => (string) ($accountAccessDetails['platform'] ?? 'Trading Account')],
                ['label' => 'Broker', 'value' => (string) ($accountAccessDetails['broker'] ?? '')],
                ['label' => 'Login ID', 'value' => (string) ($accountAccessDetails['login_id'] ?? 'Pending provisioning')],
                ['label' => 'Password', 'value' => (string) ($accountAccessDetails['password'] ?? 'Pending provisioning')],
                ['label' => 'Investor Password', 'value' => (string) ($accountAccessDetails['investor_password'] ?? 'Investor password pending')],
                ['label' => 'Server', 'value' => (string) ($accountAccessDetails['server'] ?? 'Pending provisioning')],
                ['label' => 'Account Type', 'value' => (string) ($accountAccessDetails['account_type'] ?? '')],
                ['label' => 'Account Size', 'value' => (string) ($accountAccessDetails['account_size'] ?? '')],
                $accountReference ? ['label' => 'Wolforix Reference', 'value' => (string) $accountReference] : null,
            ])),
        ])

        <p style="margin:0; color:#d5deea; font-size:14px; line-height:1.8;">
            {{ $credentialsReady
                ? 'Use these account login details to access your purchased challenge account immediately, and keep them secure.'
                : 'If any login field still shows pending or provisional information, the final trading credentials will follow automatically as soon as provisioning is completed.' }}
        </p>
    @else
        <p style="margin:0; color:#d5deea; font-size:14px; line-height:1.8;">
            We will continue with challenge-account provisioning and the next onboarding steps. Your account access details will be delivered automatically as soon as they are available.
        </p>
    @endif
</x-emails.layout>
