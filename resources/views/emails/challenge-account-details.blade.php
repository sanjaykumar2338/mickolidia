<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <h1 style="font-size: 22px; margin: 0 0 16px;">Your Challenge Account Details — Wolforix</h1>

    <p style="margin: 0 0 12px;">Hi <strong>{{ $traderName }}</strong>,</p>

    <p style="margin: 0 0 12px;">
        Thank you for joining Wolforix. Your challenge purchase has been confirmed, and your trader-facing account details are below.
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
            <td style="padding: 8px 0; color: #475569;">Account Type</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['account_type'] }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Account Size</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['account_size'] }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Wolforix Reference</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $tradingAccount->account_reference }}</td>
        </tr>
    </table>

    <p style="margin: 0 0 12px;">
        Please keep these details secure and use them only for the Wolforix challenge account assigned to you.
    </p>

    <p style="margin: 0;">Best regards,<br>The Wolforix Team</p>
</div>
