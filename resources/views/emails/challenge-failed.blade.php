<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <h1 style="font-size: 22px; margin: 0 0 16px;">Account Status Update – Challenge Failed</h1>

    <p style="margin: 0 0 12px;">Hi <strong>{{ $user->name }}</strong>,</p>

    <p style="margin: 0 0 12px;">
        We’re sorry to let you know that your Wolforix challenge account has breached one of the required risk rules.
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
            <td style="padding: 8px 0; color: #475569;">Rule breached</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['rule'] }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Threshold</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['threshold'] }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Recorded value</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['recorded_value'] }}</td>
        </tr>
    </table>

    <p style="margin: 0 0 12px;">
        This challenge account has now been permanently disabled and invalidated. Trading access for this challenge is blocked to protect the integrity of the evaluation.
    </p>

    <p style="margin: 0 0 12px;">
        If you believe this requires review or you need help understanding the rule breach, please contact our support team at
        <a href="mailto:{{ $details['support_email'] }}" style="color: #0f172a; font-weight: 700;">{{ $details['support_email'] }}</a>.
    </p>

    <p style="margin: 0;">Thank you,<br>The Wolforix Team</p>
</div>
