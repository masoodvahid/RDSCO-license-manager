<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentRecorder
{
    /**
     * Record a payment. When linked to an invoice that is now fully paid,
     * the invoice is marked paid.
     */
    public function record(
        Customer $customer,
        int $amount,
        Carbon $paidOn,
        PaymentMethod $method,
        ?Invoice $invoice = null,
        ?string $reference = null,
        ?string $notes = null,
    ): Payment {
        return DB::transaction(function () use ($customer, $amount, $paidOn, $method, $invoice, $reference, $notes): Payment {
            $payment = Payment::query()->create([
                'customer_id' => $customer->getKey(),
                'invoice_id' => $invoice?->getKey(),
                'amount' => $amount,
                'paid_on' => $paidOn,
                'method' => $method,
                'reference' => $reference,
                'notes' => $notes,
            ]);

            if ($invoice !== null && $invoice->status === InvoiceStatus::Issued && $invoice->paidAmount() >= $invoice->total) {
                $invoice->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now()])->save();
            }

            return $payment;
        });
    }

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $invoice = $payment->invoice;
            $payment->delete();

            if ($invoice !== null && $invoice->status === InvoiceStatus::Paid && $invoice->paidAmount() < $invoice->total) {
                $invoice->forceFill(['status' => InvoiceStatus::Issued, 'paid_at' => null])->save();
            }
        });
    }
}
