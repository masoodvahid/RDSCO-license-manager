@php
    use App\Support\Jalali;
    use App\Support\Money;
@endphp

<div>
    <x-page-header title="فاکتورها" description="هر زمان بخواهید، مصرف فاکتورنشده هر مشتری را فاکتور کنید">
        <x-slot:actions>
            <x-button :href="route('invoices.create')" icon="heroicon-o-document-plus" wire:navigate>صدور فاکتور</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row">
            <select wire:model.live="customer" class="field sm:w-64">
                <option value="">همه مشتری‌ها</option>
                @foreach ($customers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select wire:model.live="status" class="field sm:w-44">
                <option value="">همه وضعیت‌ها</option>
                @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </div>

        @if ($invoices->isEmpty())
            <x-empty icon="heroicon-o-document-text" title="فاکتوری پیدا نشد" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>شماره</th><th>مشتری</th><th>تاریخ صدور</th><th>دوره</th><th>مبلغ</th><th>پرداخت‌شده</th><th>وضعیت</th></tr></thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr wire:key="invoice-{{ $invoice->id }}">
                                <td><a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="link ltr-value">{{ $invoice->number }}</a></td>
                                <td>{{ $invoice->customer?->display_name }}</td>
                                <td>{{ Jalali::date($invoice->issued_at) }}</td>
                                <td class="text-xs text-gray-500">{{ Jalali::date($invoice->period_start) }} تا {{ Jalali::date($invoice->period_end) }}</td>
                                <td class="font-medium">{{ Money::format($invoice->total) }}</td>
                                <td>{{ Money::format((int) $invoice->payments_sum_amount) }}</td>
                                <td><x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $invoices->links() }}
        @endif
    </x-card>
</div>
