<div style="font-family: Arial, sans-serif; color: #0f172a;">
    <h1 style="font-size: 22px; margin-bottom: 16px;">Congratulations, you completed the Wolforix free trial.</h1>
    <p style="margin: 0 0 12px;">Hi <strong>{{ $user->name }}</strong>, your free trial model has been completed successfully.</p>
    <p style="margin: 0 0 12px;">Account reference: <strong>{{ $tradingAccount->account_reference }}</strong></p>
    <p style="margin: 0 0 12px;">You reached the 8% take-profit target within the displayed trial rules.</p>
    <p style="margin: 0 0 12px;">You can now move forward into a Simulation Account and continue under a structured evaluation model.</p>
    <p style="margin: 0;">
        <a href="{{ route('home') }}#plans" style="color:#0f172a; font-weight:700;">View Simulation Plans</a>
    </p>
</div>
