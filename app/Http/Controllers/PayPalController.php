<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\OrderFulfillmentService;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class PayPalController extends Controller
{
    public function success(
        Request $request,
        PaymentManager $paymentManager,
        OrderFulfillmentService $fulfillmentService,
    ): View|RedirectResponse {
        $token = (string) $request->query('token', '');
        $orderNumber = (string) $request->query('order', '');

        if ($token === '') {
            return redirect()
                ->route('checkout.cancel', ['order' => $orderNumber])
                ->withErrors(['payment' => __('site.checkout.errors.provider_unavailable')]);
        }

        $order = $this->resolveOrder($token, $orderNumber);

        abort_unless($order instanceof Order, 404);

        $gateway = $paymentManager->provider('paypal');

        if (! method_exists($gateway, 'captureOrder')) {
            throw new RuntimeException('Configured PayPal gateway does not support order capture.');
        }

        try {
            /** @var array<string, mixed> $payment */
            $payment = $gateway->captureOrder($token);
        } catch (Throwable $exception) {
            Log::warning('paypal.callback_capture_failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'paypal_order_id' => $token,
                'message' => $exception->getMessage(),
            ]);

            if (! $order->isPaid()) {
                $fulfillmentService->markFailed($order, [
                    'provider' => 'paypal',
                    'external_checkout_id' => $token,
                    'currency' => $order->currency,
                    'amount' => (float) $order->final_price,
                    'payload' => [
                        'source' => 'paypal_callback',
                        'message' => $exception->getMessage(),
                    ],
                ]);
            }

            return redirect()
                ->route('checkout.cancel', ['order' => $order->order_number])
                ->withErrors(['payment' => __('site.checkout.errors.provider_unavailable')]);
        }

        if (($payment['status'] ?? null) === 'paid') {
            $fulfillmentService->markPaid($order, $payment);
            $order->refresh()->loadMissing(['challengePurchase', 'user']);
        }

        return view('checkout.success', [
            'order' => $order->fresh(['challengePurchase', 'user']),
        ]);
    }

    public function cancel(Request $request, OrderFulfillmentService $fulfillmentService): View|RedirectResponse
    {
        $token = (string) $request->query('token', '');
        $orderNumber = (string) $request->query('order', '');
        $order = $this->resolveOrder($token, $orderNumber);

        abort_unless($order instanceof Order, 404);

        if (! $order->isPaid()) {
            $fulfillmentService->markCanceled($order, [
                'provider' => 'paypal',
                'external_checkout_id' => $token !== '' ? $token : $order->external_checkout_id,
                'external_payment_id' => $order->external_payment_id,
                'currency' => $order->currency,
                'amount' => (float) $order->final_price,
                'payload' => [
                    'source' => 'paypal_cancel',
                ],
            ]);

            $order->refresh();
        }

        return view('checkout.cancel', [
            'order' => $order,
        ]);
    }

    private function resolveOrder(string $token, string $orderNumber): ?Order
    {
        $query = Order::query();

        if ($orderNumber !== '') {
            $query->where('order_number', $orderNumber);
        }

        if ($token !== '') {
            if ($orderNumber !== '') {
                $query->orWhere('external_checkout_id', $token);
            } else {
                $query->where('external_checkout_id', $token);
            }
        }

        return $query->latest('id')->first();
    }
}
