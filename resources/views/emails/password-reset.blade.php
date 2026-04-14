<x-emails.layout
    :status="__('Password Reset')"
    :title="__('Reset your Wolforix password')"
    :primary-url="$actionUrl"
    :primary-label="__('Create a New Password')"
    :secondary-url="route('login')"
    :secondary-label="__('Back to Login')"
>
    <x-slot:intro>
        Hi <strong style="color:#ffffff;">{{ $user->name }}</strong>, we received a request to reset the password for your Wolforix account.
    </x-slot:intro>

    <x-slot:cards>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <tr>
                <td width="33.33%" style="padding-right:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Action</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">Reset Password</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">Use the secure link below to choose a new password.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding:0 4px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Valid For</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">{{ $expireMinutes }} min</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">After that, request a fresh reset link.</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="33.33%" style="padding-left:8px; vertical-align:top;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; min-height:118px; border:1px solid rgba(244,183,74,0.12); border-radius:22px; background:linear-gradient(180deg, rgba(16,23,38,0.92) 0%, rgba(9,14,24,0.88) 100%);">
                        <tr>
                            <td style="padding:18px 18px 16px 18px;">
                                <p style="margin:0; color:#f4b74a; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;">Security</p>
                                <p style="margin:10px 0 0 0; color:#ffffff; font-size:19px; font-weight:700;">Ignore if Needed</p>
                                <p style="margin:8px 0 0 0; color:#94a3b8; font-size:13px; line-height:1.7;">If you didn’t request this, no changes will be made.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </x-slot:cards>

    <p style="margin:0; color:#f4b74a; font-size:12px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase;">Security Request</p>
    <p style="margin:12px 0 0 0; color:#ffffff; font-size:24px; font-weight:700; line-height:1.3;">
        Finish the reset from the secure link
    </p>

    @include('emails.partials.details-table', [
        'rows' => [
            ['label' => 'Email', 'value' => (string) $user->email],
            ['label' => 'Link Lifetime', 'value' => (string) $expireMinutes.' minutes'],
            ['label' => 'Requested Action', 'value' => 'Create a new password'],
        ],
    ])

    <p style="margin:0; color:#d5deea; font-size:14px; line-height:1.8;">
        If you did not request a password reset, you can safely ignore this email and your password will remain unchanged.
    </p>
</x-emails.layout>
