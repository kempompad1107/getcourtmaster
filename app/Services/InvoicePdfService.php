<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SubscriptionInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfService
{
    public function subscriptionInvoice(SubscriptionInvoice $invoice): Response
    {
        $invoice->loadMissing('tenant', 'subscription.plan');
        $pdf = Pdf::loadView('pdf.subscription-invoice', ['invoice' => $invoice])
            ->setPaper('a4');
        return $pdf->download('invoice-' . $invoice->invoice_number . '.pdf');
    }

    public function officialReceipt(Payment $payment): Response
    {
        $payment->loadMissing('customer', 'payable');
        $pdf = Pdf::loadView('pdf.official-receipt', ['payment' => $payment])
            ->setPaper('a5');
        return $pdf->download('receipt-' . ($payment->receipt_number ?: $payment->id) . '.pdf');
    }
}
