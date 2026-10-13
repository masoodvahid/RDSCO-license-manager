<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Services\Billing\InvoiceIssuer;
use Livewire\Component;

class Show extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }

    public function void(InvoiceIssuer $issuer): void
    {
        if ($this->invoice->status === InvoiceStatus::Void) {
            return;
        }

        $issuer->void($this->invoice);
        ActivityLog::record('invoice.voided', "ابطال فاکتور {$this->invoice->number}", $this->invoice);

        $this->dispatch('toast', type: 'success', message: 'فاکتور باطل شد و مصرف آن دوباره قابل فاکتور شدن است.');
    }

    public function render()
    {
        $this->invoice->load(['customer', 'items', 'payments']);

        return view('livewire.invoices.show', [
            'paid' => (int) $this->invoice->payments->sum('amount'),
        ])->title('فاکتور '.$this->invoice->number);
    }
}
