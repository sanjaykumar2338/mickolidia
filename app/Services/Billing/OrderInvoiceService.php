<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\TradingAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderInvoiceService
{
    public function __construct(
        private readonly InvoicePdfRenderer $pdfRenderer,
    ) {
    }

    public function ensureForOrder(Order $order): Invoice
    {
        $invoice = DB::transaction(function () use ($order): Invoice {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->with(['challengePurchase.tradingAccounts', 'invoice'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            /** @var Invoice $invoice */
            $invoice = Invoice::query()->firstOrNew([
                'order_id' => $lockedOrder->id,
            ]);

            $linkedAccount = $lockedOrder->challengePurchase?->tradingAccounts
                ->sortByDesc('created_at')
                ->first();

            $invoice->fill([
                'user_id' => $lockedOrder->user_id,
                'challenge_purchase_id' => $lockedOrder->challengePurchase?->id,
                'trading_account_id' => $linkedAccount instanceof TradingAccount ? $linkedAccount->id : null,
                'currency' => strtoupper((string) ($lockedOrder->currency ?: 'USD')),
                'subtotal' => (float) $lockedOrder->final_price,
                'tax_amount' => (float) data_get($lockedOrder->metadata, 'tax.amount', 0),
                'total' => (float) $lockedOrder->final_price + (float) data_get($lockedOrder->metadata, 'tax.amount', 0),
                'payment_method' => ucfirst((string) $lockedOrder->payment_provider),
                'transaction_id' => $lockedOrder->external_payment_id ?: $lockedOrder->external_checkout_id ?: $lockedOrder->order_number,
                'status' => 'paid',
                'issued_at' => $invoice->issued_at ?? now(),
                'pdf_disk' => $invoice->pdf_disk ?: 'public',
                'meta' => array_merge($invoice->meta ?? [], [
                    'order_number' => $lockedOrder->order_number,
                    'challenge_type' => $lockedOrder->challenge_type,
                    'account_size' => $lockedOrder->account_size,
                    'payment_provider' => $lockedOrder->payment_provider,
                ]),
            ]);

            $invoice->save();

            if (! filled($invoice->invoice_number)) {
                $invoice->forceFill([
                    'invoice_number' => 'WF-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
                ])->save();
            }

            return $invoice;
        });

        return $this->ensurePdf($invoice);
    }

    public function ensurePdf(Invoice $invoice): Invoice
    {
        $invoice->loadMissing(['order', 'user', 'challengePurchase', 'tradingAccount']);

        $disk = Storage::disk((string) ($invoice->pdf_disk ?: 'public'));
        $path = (string) ($invoice->pdf_path ?: 'invoices/'.($invoice->invoice_number ?: 'WF-'.$invoice->id).'.pdf');

        if (! $disk->exists($path)) {
            $disk->put($path, $this->pdfRenderer->render($invoice));

            $invoice->forceFill([
                'pdf_disk' => $invoice->pdf_disk ?: 'public',
                'pdf_path' => $path,
                'pdf_generated_at' => now(),
            ])->save();
        } elseif (! filled($invoice->pdf_path)) {
            $invoice->forceFill([
                'pdf_path' => $path,
                'pdf_generated_at' => $invoice->pdf_generated_at ?? now(),
            ])->save();
        }

        return $invoice->fresh(['order', 'user', 'challengePurchase', 'tradingAccount']) ?? $invoice;
    }
}
