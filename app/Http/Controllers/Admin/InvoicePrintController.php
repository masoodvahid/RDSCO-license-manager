<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Contracts\View\View;

class InvoicePrintController extends Controller
{
    public function __invoke(Invoice $invoice): View
    {
        $invoice->load(['customer', 'items']);

        return view('invoices.print', [
            'invoice' => $invoice,
            'company' => [
                'name' => Setting::get('company.name'),
                'national_id' => Setting::get('company.national_id'),
                'economic_code' => Setting::get('company.economic_code'),
                'address' => Setting::get('company.address'),
                'phone' => Setting::get('company.phone'),
                'payment_note' => Setting::get('company.payment_note'),
            ],
        ]);
    }
}
