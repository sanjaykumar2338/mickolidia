<div style="font-family: Arial, sans-serif; color: #0f172a;">
    <h1 style="font-size: 22px; margin-bottom: 16px;">Wolforix Challenge Purchase Confirmed</h1>
    <p style="margin: 0 0 12px;">Your order <strong>{{ $order->order_number }}</strong> has been confirmed.</p>
    <p style="margin: 0 0 12px;">Plan: <strong>{{ config('wolforix.challenge_catalog.'.$order->challenge_type.'.label', $order->challenge_type === 'one_step' ? '1-Step Instant' : '2-Step Pro') }} / {{ (int) ($order->account_size / 1000) }}K</strong></p>
    <p style="margin: 0 0 12px;">Amount paid: <strong>{{ strtoupper($order->currency) }} {{ number_format((float) $order->final_price, 2) }}</strong></p>
    <p style="margin: 0 0 12px;">Provider: <strong>{{ ucfirst($order->payment_provider) }}</strong></p>
    <p style="margin: 0;">We will continue with challenge-account provisioning and the next onboarding steps.</p>
</div>
