<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <h1 style="font-size: 22px; margin: 0 0 16px;">You’ve Passed Phase 1 — 2-Step Pro</h1>

    <p style="margin: 0 0 12px;">Hi <strong>{{ $traderName }}</strong>,</p>

    <p style="margin: 0 0 12px;">
        Congratulations. You have successfully passed Phase 1 of the Wolforix 2-Step Pro evaluation.
    </p>

    <table style="width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 14px;">
        <tr>
            <td style="padding: 8px 0; color: #475569;">Account ID</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $tradingAccount->account_reference }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Challenge</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['plan'] }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Completed Phase</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['completed_phase'] }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #475569;">Next Step</td>
            <td style="padding: 8px 0; font-weight: 700;">{{ $details['next_phase'] }}</td>
        </tr>
    </table>

    <p style="margin: 0 0 12px;">
        Your Phase 2 account details will be sent automatically once the Phase 2 account is available.
    </p>

    <p style="margin: 0;">Best regards,<br>The Wolforix Team</p>
</div>
