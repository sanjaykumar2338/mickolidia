<x-emails.layout
    :status="__('Trial Ended')"
    :title="__('Your Wolforix free trial has ended')"
    :primary-url="route('trial.register')"
    :primary-label="__('Start a New Free Trial')"
    :secondary-url="route('home').'#plans'"
    :secondary-label="__('View Simulation Plans')"
>
    <x-slot:intro>
        Hi <strong style="color:#ffffff;">{{ $user->name }}</strong>, your free trial model is no longer active because the displayed rules were breached.
    </x-slot:intro>

    <x-slot:cards>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <tr>
                <td width="33.33%" style="padding-right:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Account Reference</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $tradingAccount->account_reference }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">The trial session has been closed.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding:0 4px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Reason</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $reason }}</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">The displayed trial rules were not met.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding-left:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Next Step</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">Try Again</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">You can start a fresh free trial whenever you’re ready.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </x-slot:cards>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">Free Trial Result</p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        Practice access has ended
    </p>

    @include('emails.partials.details-table', [
        'rows' => [
            ['label' => 'Account Reference', 'value' => (string) $tradingAccount->account_reference],
            ['label' => 'Reason', 'value' => (string) $reason],
            ['label' => 'Next Step', 'value' => 'Start a new free trial'],
        ],
    ])

    <p style="margin:0; color:#d5deea; font-size:14px; line-height:1.8;">
        You can start a fresh free trial to continue practicing under the same rule logic.
    </p>
</x-emails.layout>
