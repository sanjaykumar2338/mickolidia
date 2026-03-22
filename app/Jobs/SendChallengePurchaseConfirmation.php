<?php

namespace App\Jobs;

use App\Mail\ChallengePurchaseConfirmationMail;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendChallengePurchaseConfirmation implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId)
    {
    }

    public function handle(): void
    {
        $order = Order::query()
            ->with(['challengePurchase', 'user'])
            ->find($this->orderId);

        if (! $order instanceof Order || ! $order->challengePurchase) {
            return;
        }

        Mail::to($order->email)->send(new ChallengePurchaseConfirmationMail($order));
    }
}
