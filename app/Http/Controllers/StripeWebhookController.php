<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\OrderFulfillmentService;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentManager $paymentManager,
        OrderFulfillmentService $fulfillmentService,
    ): JsonResponse {
        try {
            $event = $paymentManager->provider('stripe')->parseWebhook(
                $request->getContent(),
                $request->header('Stripe-Signature'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['received' => false], 400);
        }

        $order = Order::query()
            ->where('id', $event['order_id'] ?? 0)
            ->orWhere('order_number', $event['order_number'] ?? '')
            ->orWhere('external_checkout_id', $event['external_checkout_id'] ?? '')
            ->first();

        if (! $order instanceof Order) {
            return response()->json(['received' => false], 404);
        }

        match ($event['status'] ?? null) {
            'paid' => $fulfillmentService->markPaid($order, $event),
            'failed' => $fulfillmentService->markFailed($order, $event),
            'canceled' => $fulfillmentService->markCanceled($order, $event),
            default => null,
        };

        return response()->json(['received' => true]);
    }
}
