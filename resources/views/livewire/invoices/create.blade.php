@php
    use App\Support\Jalali;
    use App\Support\Money;
@endphp

<div>
    <x-page-header title="صدور فاکتور" description="همه مصرف قابل شارژ و فاکتورنشده مشتری تا همین لحظه (یا تا تاریخ انتخابی) فاکتور می‌شود.">
        <x-slot:breadcrumb><a href="{{ route('invoices.index') }}" wire:navigate class="hover:text-gray-700">فاکتورها</a></x-slot:breadcrumb>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
            <x-card title="تنظیمات فاکتور">
                <div class="space-y-4">
                    <x-select name="customer_id" label="مشتری" :options="$customers" placeholder="انتخاب مشتری" live />
                    <x-input name="until" label="تا تاریخ (اختیاری)" ltr placeholder="1405/06/31" modifier="live.debounce.600ms" hint="خالی = تا همین لحظه" />
                    <x-input name="discount" label="تخفیف (ریال)" ltr inputmode="numeric" modifier="live.debounce.400ms" />
                    <x-textarea name="notes" label="توضیحات فاکتور" rows="2" />
                </div>
            </x-card>

            @if ($pending->isNotEmpty())
                <x-card title="مشتری‌های دارای مصرف فاکتورنشده" :padding="false">
                    @foreach ($pending as $row)
                        @php($pendingCustomer = \App\Models\Customer::withTrashed()->find($row->customer_id))
                        <button type="button" wire:click="$set('customer_id', '{{ $row->customer_id }}')" class="flex w-full items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 text-right text-sm last:border-0 hover:bg-gray-50">
                            <span class="truncate text-gray-800">{{ $pendingCustomer?->display_name }}</span>
                            <span class="shrink-0 text-xs text-gray-500">{{ number_format($row->requests) }} · {{ Money::format((int) $row->amount) }}</span>
                        </button>
                    @endforeach
                </x-card>
            @endif
        </div>

        <div class="lg:col-span-2">
            <x-card title="پیش‌نمایش">
                @if (! $customer)
                    <x-empty icon="heroicon-o-user" title="یک مشتری انتخاب کنید" />
                @elseif ($preview['count'] === 0)
                    <x-empty icon="heroicon-o-check-circle" title="مصرف فاکتورنشده‌ای وجود ندارد" description="همه مصرف قابل شارژ این مشتری قبلاً فاکتور شده است." />
                @else
                    <div class="mb-4 text-sm text-gray-600">
                        <span class="font-medium text-gray-900">{{ $customer->display_name }}</span>
                        · دوره {{ Jalali::dateTime($preview['from']) }} تا {{ Jalali::dateTime($preview['to']) }}
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="table-base">
                            <thead><tr><th>شرح</th><th>تعداد</th><th>فی (ریال)</th><th>مبلغ</th></tr></thead>
                            <tbody>
                                @foreach ($preview['lines'] as $line)
                                    <tr>
                                        <td>{{ $line['service'] }}</td>
                                        <td>{{ number_format($line['quantity']) }}</td>
                                        <td>{{ number_format($line['unit_price']) }}</td>
                                        <td class="font-medium">{{ Money::format($line['amount']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <dl class="mr-auto mt-4 max-w-xs space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">جمع</dt><dd>{{ Money::format($preview['subtotal']) }}</dd></div>
                        @if ($discountAmount > 0)
                            <div class="flex justify-between"><dt class="text-gray-500">تخفیف</dt><dd class="text-red-600">− {{ Money::format($discountAmount) }}</dd></div>
                        @endif
                        @if ($taxRate > 0)
                            <div class="flex justify-between"><dt class="text-gray-500">مالیات بر ارزش افزوده ({{ rtrim(rtrim(number_format($taxRate, 2), '0'), '.') }}٪)</dt><dd>{{ Money::format($taxAmount) }}</dd></div>
                        @endif
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold"><dt>مبلغ نهایی</dt><dd>{{ Money::format($total) }}</dd></div>
                    </dl>

                    <div class="mt-6 flex justify-end">
                        <x-button wire:click="issue" icon="heroicon-o-document-check" wire:confirm="فاکتور صادر شود؟ مصرف این دوره به فاکتور متصل می‌شود.">صدور فاکتور</x-button>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</div>
