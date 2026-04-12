<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <h1 style="font-size: 22px; margin: 0 0 16px;">Congratulations — You’ve Passed the Evaluation</h1>

    <p style="margin: 0 0 12px;">Hi <strong>{{ $user->name }}</strong>,</p>

    <p style="margin: 0 0 12px;">
        Congratulations. You have successfully passed the Wolforix evaluation and reached funded account status.
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
            <td style="padding: 8px 0; color: #475569;">Status</td>
            <td style="padding: 8px 0; font-weight: 700;">Funded account review</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Profit target</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['profit_target'] }} ({{ $details['profit_target_percent'] }})</td>
        </tr>
    </table>

    <p style="margin: 0 0 12px;">
        Our team will complete the funded account review and follow up with the next step. Please keep an eye on your email for any additional instructions.
    </p>

    @if (! empty($certificate))
        <p style="margin: 0 0 12px;">
            Your personalized Wolforix funded trader certificate is attached to this email.
        </p>
    @endif

    <p style="margin: 0 0 12px;">
        If you have any questions, contact us at
        <a href="mailto:{{ $details['support_email'] }}" style="color: #0f172a; font-weight: 700;">{{ $details['support_email'] }}</a>.
    </p>

    <p style="margin: 0;">Well done,<br>The Wolforix Team</p>
</div>
