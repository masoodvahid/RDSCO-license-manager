@php
    use App\Support\Jalali;
    use App\Support\Money;
@endphp

<div>
    <x-page-header title="پرداخت‌ها" description="وجوه دریافتی از مشتری‌ها؛ در صورت اتصال به فاکتور، وضعیت فاکتور خودکار به‌روز می‌شود">
        <x-slot:actions>
            <x-button wire:click="create" icon="heroicon-o-plus">ثبت پرداخت</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="border-b border-gray-100 p-4">
            <select wire:model.live="filterCustomer" class="field sm:w-64">
                <option value="">همه مشتری‌ها</option>
                @foreach ($customers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
        </div>

        @if ($payments->isEmpty())
            <x-empty icon="heroicon-o-banknotes" title="پرداختی ثبت نشده است" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>تاریخ</th><th>مشتری</th><th>مبلغ</th><th>روش</th><th>فاکتور</th><th>مرجع</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr wire:key="payment-{{ $payment->id }}">
                                <td>{{ Jalali::date($payment->paid_on) }}</td>
                                <td>{{ $payment->customer?->display_name }}</td>
                                <td class="font-medium text-emerald-700">{{ Money::format($payment->amount) }}</td>
                                <td>{{ $payment->method->label() }}</td>
                                <td>
                                    @if ($payment->invoice)
                                        <a href="{{ route('invoices.show', $payment->invoice) }}" wire:navigate class="link ltr-value">{{ $payment->invoice->number }}</a>
                                    @else
                                        <span class="text-gray-400">علی‌الحساب</span>
                                    @endif
                                </td>
                                <td class="text-xs text-gray-500">{{ $payment->reference ?? '—' }}</td>
                                <td class="text-left">
                                    <x-button variant="ghost" size="sm" class="text-red-600" wire:click="delete({{ $payment->id }})" wire:confirm="این پرداخت حذف شود؟">حذف</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $payments->links() }}
        @endif
    </x-card>

    <x-modal wire:model="showForm" title="ثبت پرداخت">
        <form id="payment-form" wire:submit="save" class="space-y-4">
            <x-select name="customer_id" label="مشتری" :options="$customers" placeholder="انتخاب مشتری" live required />
            <x-select name="invoice_id" label="بابت فاکتور" :options="$openInvoices" placeholder="علی‌الحساب (بدون فاکتور)" live />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="amount" label="مبلغ (ریال)" ltr inputmode="numeric" required />
                <x-input name="paid_on" label="تاریخ پرداخت" ltr placeholder="1405/01/31" required />
                <x-select name="method" label="روش پرداخت" :options="$methods" />
                <x-input name="reference" label="شماره پیگیری / مرجع" ltr />
            </div>
            <x-textarea name="notes" label="توضیح" rows="2" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">انصراف</x-button>
            <x-button type="submit" form="payment-form" wire:target="save">ثبت</x-button>
        </x-slot:footer>
    </x-modal>
</div>
