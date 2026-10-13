@php
    use App\Support\Jalali;
    use App\Support\Money;
    $remaining = max(0, $invoice->total - $paid);
@endphp

<div>
    <x-page-header :title="'فاکتور '.$invoice->number" :description="$invoice->customer?->display_name">
        <x-slot:breadcrumb><a href="{{ route('invoices.index') }}" wire:navigate class="hover:text-gray-700">فاکتورها</a></x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge>
            <x-button variant="secondary" :href="route('invoices.print', $invoice)" target="_blank" icon="heroicon-o-printer">چاپ</x-button>
            @if ($invoice->status === \App\Enums\InvoiceStatus::Issued)
                <x-button :href="route('payments.index', ['customer' => $invoice->customer_id, 'invoice' => $invoice->id, 'create' => 1])" icon="heroicon-o-banknotes" wire:navigate>ثبت پرداخت</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="اقلام" :padding="false">
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>شرح</th><th>تعداد</th><th>فی (ریال)</th><th>مبلغ</th></tr></thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td>{{ $item->description }}</td>
                                    <td>{{ number_format($item->quantity) }}</td>
                                    <td>{{ number_format($item->unit_price) }}</td>
                                    <td class="font-medium">{{ Money::format($item->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <dl class="mr-auto max-w-xs space-y-2 px-5 py-4 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">جمع</dt><dd>{{ Money::format($invoice->subtotal) }}</dd></div>
                    @if ($invoice->discount > 0)
                        <div class="flex justify-between"><dt class="text-gray-500">تخفیف</dt><dd class="text-red-600">− {{ Money::format($invoice->discount) }}</dd></div>
                    @endif
                    @if ($invoice->tax_amount > 0)
                        <div class="flex justify-between"><dt class="text-gray-500">مالیات ({{ rtrim(rtrim((string) $invoice->tax_rate, '0'), '.') }}٪)</dt><dd>{{ Money::format($invoice->tax_amount) }}</dd></div>
                    @endif
                    <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold"><dt>مبلغ نهایی</dt><dd>{{ Money::format($invoice->total) }}</dd></div>
                </dl>
            </x-card>

            <x-card title="پرداخت‌ها" :padding="false">
                @forelse ($invoice->payments as $payment)
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 text-sm last:border-0">
                        <span>{{ Jalali::date($payment->paid_on) }}</span>
                        <span class="text-xs text-gray-500">{{ $payment->method->label() }} {{ $payment->reference ? '· '.$payment->reference : '' }}</span>
                        <span class="font-medium text-emerald-700">{{ Money::format($payment->amount) }}</span>
                    </div>
                @empty
                    <x-empty icon="heroicon-o-banknotes" title="پرداختی برای این فاکتور ثبت نشده است" />
                @endforelse
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="خلاصه">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">مشتری</dt><dd><a href="{{ route('customers.show', $invoice->customer_id) }}" wire:navigate class="link">{{ $invoice->customer?->display_name }}</a></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">تاریخ صدور</dt><dd>{{ Jalali::dateTime($invoice->issued_at) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">دوره مصرف</dt><dd class="text-xs">{{ Jalali::date($invoice->period_start) }} تا {{ Jalali::date($invoice->period_end) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">پرداخت‌شده</dt><dd>{{ Money::format($paid) }}</dd></div>
                    <div class="flex justify-between font-medium"><dt class="text-gray-500">مانده</dt><dd @class(['text-red-600' => $remaining > 0])>{{ Money::format($remaining) }}</dd></div>
                    @if ($invoice->voided_at)
                        <div class="flex justify-between"><dt class="text-gray-500">تاریخ ابطال</dt><dd>{{ Jalali::dateTime($invoice->voided_at) }}</dd></div>
                    @endif
                    @if ($invoice->notes)
                        <div class="rounded-lg bg-gray-50 p-3 text-xs whitespace-pre-line text-gray-600">{{ $invoice->notes }}</div>
                    @endif
                </dl>
                <a href="{{ route('usage.index', ['customer' => $invoice->customer_id]) }}" wire:navigate class="link mt-4 inline-block text-xs">ریز درخواست‌های مشتری</a>
            </x-card>

            @if ($invoice->status !== \App\Enums\InvoiceStatus::Void)
                <x-card title="ابطال فاکتور">
                    <p class="mb-3 text-xs text-gray-500">با ابطال، مصرف این فاکتور آزاد می‌شود تا دوباره فاکتور شود. پرداخت‌های ثبت‌شده به‌عنوان بستانکاری مشتری باقی می‌مانند.</p>
                    <x-button variant="danger" size="sm" wire:click="void" wire:confirm="این فاکتور باطل شود؟">ابطال</x-button>
                </x-card>
            @endif
        </div>
    </div>
</div>
