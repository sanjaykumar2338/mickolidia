<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Billing\OrderInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardInvoiceController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, OrderInvoiceService $invoiceService): StreamedResponse
    {
        abort_unless((int) $invoice->user_id === (int) $request->user()?->id, 403);

        $invoice = $invoiceService->ensurePdf($invoice);
        $diskName = (string) ($invoice->pdf_disk ?: 'public');
        $path = (string) $invoice->pdf_path;

        abort_if($path === '' || ! Storage::disk($diskName)->exists($path), 404);

        return Storage::disk($diskName)->download(
            $path,
            ($invoice->invoice_number ?: 'wolforix-invoice').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
