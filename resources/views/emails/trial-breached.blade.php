<div style="font-family: Arial, sans-serif; color: #0f172a;">
    <h1 style="font-size: 22px; margin-bottom: 16px;">Your Wolforix free trial has ended.</h1>
    <p style="margin: 0 0 12px;">Hi <strong>{{ $user->name }}</strong>, your free trial model is no longer active because the displayed rules were breached.</p>
    <p style="margin: 0 0 12px;">Account reference: <strong>{{ $tradingAccount->account_reference }}</strong></p>
    <p style="margin: 0 0 12px;">Reason: <strong>{{ $reason }}</strong></p>
    <p style="margin: 0 0 12px;">You can start a fresh free trial to continue practicing under the same rule logic.</p>
    <p style="margin: 0;">
        <a href="{{ route('trial.register') }}" style="color:#0f172a; font-weight:700;">Start a New Free Trial</a>
    </p>
</div>
