<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <h1 style="font-size: 22px; margin: 0 0 16px;">Account Status Update – Challenge Passed</h1>

    <p style="margin: 0 0 12px;">Hi <strong>{{ $user->name }}</strong>,</p>

    <p style="margin: 0 0 12px;">
        Congratulations. Your Wolforix challenge account has reached the required completion criteria and has been marked as passed.
    </p>

    <table style="width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 14px;">
        <tr>
            <td style="padding: 8px 0; color: #475569;">Account ID</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $tradingAccount->account_reference }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Plan</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['plan'] }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Completed phase</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['phase'] }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Profit target</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['profit_target'] }} ({{ $details['profit_target_percent'] }})</td>
        </tr>
    </table>

    <p style="margin: 0 0 12px;">
        Our team will review the account and follow up with the next step. Please keep an eye on your email for any additional instructions.
    </p>

    <p style="margin: 0 0 12px;">
        If you have any questions, contact us at
        <a href="mailto:{{ $details['support_email'] }}" style="color: #0f172a; font-weight: 700;">{{ $details['support_email'] }}</a>.
    </p>

    <p style="margin: 0;">Well done,<br>The Wolforix Team</p>
</div>
