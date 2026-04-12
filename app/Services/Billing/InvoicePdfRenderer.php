<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Order;

class InvoicePdfRenderer
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['order', 'user', 'challengePurchase', 'tradingAccount']);

        $order = $invoice->order;
        $lines = $this->invoiceLines($invoice, $order);
        $content = $this->contentStream($lines);

        return $this->pdfDocument($content);
    }

    /**
     * @return list<array{text:string,x:int,y:int,size:int,font:string}>
     */
    private function invoiceLines(Invoice $invoice, ?Order $order): array
    {
        $currency = strtoupper((string) ($invoice->currency ?: $order?->currency ?: 'USD'));
        $challengeType = (string) ($order?->challenge_type ?: $invoice->challengePurchase?->challenge_type ?: 'challenge');
        $challengeTypeLabel = (string) config(
            "wolforix.challenge_catalog.{$challengeType}.label",
            $challengeType === 'one_step' ? '1-Step Instant' : '2-Step Pro',
        );
        $accountSize = (int) ($order?->account_size ?: $invoice->challengePurchase?->account_size ?: 0);
        $paymentMethod = $invoice->payment_method ?: ucfirst((string) ($order?->payment_provider ?: 'Payment'));
        $transactionId = $invoice->transaction_id ?: ($order?->external_payment_id ?: $order?->external_checkout_id ?: $order?->order_number ?: 'N/A');
        $issuedAt = $invoice->issued_at ?? $invoice->created_at ?? now();

        return [
            ['text' => 'WOLFORIX(R) - INVOICE', 'x' => 56, 'y' => 770, 'size' => 22, 'font' => 'bold'],
            ['text' => 'Invoice ID: '.($invoice->invoice_number ?: 'WF-PENDING'), 'x' => 56, 'y' => 730, 'size' => 11, 'font' => 'regular'],
            ['text' => 'Date: '.$issuedAt->format('d/m/Y'), 'x' => 56, 'y' => 712, 'size' => 11, 'font' => 'regular'],
            ['text' => 'BILL TO', 'x' => 56, 'y' => 670, 'size' => 12, 'font' => 'bold'],
            ['text' => 'Name: '.($order?->full_name ?: $invoice->user?->name ?: 'Trader'), 'x' => 56, 'y' => 648, 'size' => 11, 'font' => 'regular'],
            ['text' => 'Email: '.($order?->email ?: $invoice->user?->email ?: 'N/A'), 'x' => 56, 'y' => 630, 'size' => 11, 'font' => 'regular'],
            ['text' => 'PRODUCT DETAILS', 'x' => 56, 'y' => 586, 'size' => 12, 'font' => 'bold'],
            ['text' => 'Product: Wolforix Challenge - '.$challengeTypeLabel, 'x' => 56, 'y' => 564, 'size' => 11, 'font' => 'regular'],
            ['text' => 'Account Size: '.$this->money($accountSize, $currency), 'x' => 56, 'y' => 546, 'size' => 11, 'font' => 'regular'],
            ['text' => 'PAYMENT DETAILS', 'x' => 56, 'y' => 502, 'size' => 12, 'font' => 'bold'],
            ['text' => 'Payment Method: '.$paymentMethod, 'x' => 56, 'y' => 480, 'size' => 11, 'font' => 'regular'],
            ['text' => 'Transaction ID: '.$transactionId, 'x' => 56, 'y' => 462, 'size' => 11, 'font' => 'regular'],
            ['text' => 'SUMMARY', 'x' => 56, 'y' => 418, 'size' => 12, 'font' => 'bold'],
            ['text' => 'Price: '.$this->money((float) $invoice->subtotal, $currency), 'x' => 56, 'y' => 396, 'size' => 11, 'font' => 'regular'],
            ['text' => 'Tax (if applicable): '.$this->money((float) $invoice->tax_amount, $currency), 'x' => 56, 'y' => 378, 'size' => 11, 'font' => 'regular'],
            ['text' => 'Total Paid: '.$this->money((float) $invoice->total, $currency), 'x' => 56, 'y' => 356, 'size' => 13, 'font' => 'bold'],
            ['text' => 'STATUS', 'x' => 56, 'y' => 312, 'size' => 12, 'font' => 'bold'],
            ['text' => str($invoice->status ?: 'paid')->headline()->toString(), 'x' => 56, 'y' => 290, 'size' => 12, 'font' => 'bold'],
            ['text' => 'WOLFORIX(R)', 'x' => 56, 'y' => 104, 'size' => 11, 'font' => 'bold'],
            ['text' => 'Elite Trading Environment', 'x' => 56, 'y' => 86, 'size' => 10, 'font' => 'regular'],
            ['text' => 'Support: support@wolforix.com', 'x' => 56, 'y' => 68, 'size' => 10, 'font' => 'regular'],
            ['text' => 'Website: www.wolforix.com', 'x' => 56, 'y' => 50, 'size' => 10, 'font' => 'regular'],
        ];
    }

    /**
     * @param  list<array{text:string,x:int,y:int,size:int,font:string}>  $lines
     */
    private function contentStream(array $lines): string
    {
        $stream = "q\n";
        $stream .= "0.95 0.69 0.20 RG 56 746 500 1 re S\n";
        $stream .= "0.12 0.15 0.21 RG 56 690 500 1 re S\n";
        $stream .= "0.12 0.15 0.21 RG 56 522 500 1 re S\n";
        $stream .= "0.12 0.15 0.21 RG 56 438 500 1 re S\n";
        $stream .= "0.95 0.69 0.20 RG 56 334 500 1 re S\n";

        foreach ($lines as $line) {
            $font = $line['font'] === 'bold' ? 'F2' : 'F1';
            $stream .= sprintf(
                "BT /%s %d Tf %d %d Td (%s) Tj ET\n",
                $font,
                $line['size'],
                $line['x'],
                $line['y'],
                $this->escapePdfText($line['text']),
            );
        }

        return $stream."Q\n";
    }

    private function pdfDocument(string $content): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            "<< /Length ".strlen($content)." >>\nstream\n{$content}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        $encoded = function_exists('iconv')
            ? iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text)
            : $text;

        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $encoded !== false ? $encoded : $text,
        );
    }

    private function money(float $value, string $currency): string
    {
        return strtoupper($currency).' '.number_format($value, 2);
    }
}
