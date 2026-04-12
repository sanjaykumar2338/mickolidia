<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <h1 style="font-size: 22px; margin: 0 0 16px;">Your Phase 2 Account Details — Wolforix 2-Step Pro</h1>

    <p style="margin: 0 0 12px;">Hi <strong>{{ $traderName }}</strong>,</p>

    <p style="margin: 0 0 12px;">
        Your Phase 2 account is ready. Please use the account details below to continue your Wolforix 2-Step Pro evaluation.
    </p>

    <table style="width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 14px;">
        <tr>
            <td style="padding: 8px 0; color: #475569;">Platform</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['platform'] }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Login ID</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['login_id'] }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Password</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['password'] }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Server</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['server'] }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Account ID</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $tradingAccount->account_reference }}</td>
        </tr>
    </table>

    <p style="margin: 0 0 12px;">
        Keep these credentials secure and continue following the Wolforix risk rules for Phase 2.
    </p>

    <p style="margin: 0;">Best regards,<br>The Wolforix Team</p>
</div>
