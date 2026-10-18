@php
    use App\Support\Jalali;
    use App\Support\Money;
@endphp

<div>
    <x-page-header title="مصرف سرویس‌ها" description="دفتر ثبت همه درخواست‌های سرویس‌های پولی؛ هر ردیف با قیمت همان لحظه">
        <x-slot:actions>
            <x-button variant="secondary" icon="heroicon-o-arrow-down-tray" wire:click="export">خروجی CSV</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="grid gap-3 border-b border-gray-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <select wire:model.live="customer" class="field">
                <option value="">همه مشتری‌ها</option>
                @foreach ($customers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select wire:model.live="service" class="field">
                <option value="">همه سرویس‌ها</option>
                @foreach ($services as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select wire:model.live="status" class="field">
                <option value="">همه نتیجه‌ها</option>
                @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            <select wire:model.live="billing" class="field">
                <option value="">وضعیت صورت‌حساب: همه</option>
                <option value="uninvoiced">قابل شارژ، فاکتورنشده</option>
                <option value="invoiced">فاکتورشده</option>
                <option value="free">رایگان (خطا)</option>
            </select>
            <input type="text" dir="ltr" wire:model.live.debounce.600ms="from" placeholder="از تاریخ 1405/01/01" class="field text-left">
            <input type="text" dir="ltr" wire:model.live.debounce.600ms="to" placeholder="تا تاریخ 1405/01/31" class="field text-left">
            <input type="text" dir="ltr" inputmode="numeric" wire:model.live.debounce.600ms="subject" placeholder="کد ملی یا شماره همراه" class="field text-left placeholder:text-right">
            <div class="flex items-center gap-2">
                @if ($license !== '')
                    <x-badge color="info">فیلتر یک لایسنس</x-badge>
                @endif
                <x-button variant="ghost" size="sm" wire:click="resetFilters">حذف فیلترها</x-button>
            </div>
        </div>

        <div class="grid grid-cols-3 divide-x divide-x-reverse divide-gray-100 border-b border-gray-100 text-center">
            <div class="p-4"><div class="text-xs text-gray-500">کل درخواست‌ها</div><div class="mt-1 text-lg font-semibold">{{ number_format((int) $totals->requests) }}</div></div>
            <div class="p-4"><div class="text-xs text-gray-500">قابل شارژ</div><div class="mt-1 text-lg font-semibold">{{ number_format((int) $totals->billable_requests) }}</div></div>
            <div class="p-4"><div class="text-xs text-gray-500">مبلغ</div><div class="mt-1 text-lg font-semibold">{{ Money::format((int) $totals->amount) }}</div></div>
        </div>

        @if ($records->isEmpty())
            <x-empty icon="heroicon-o-chart-bar" title="درخواستی با این فیلترها ثبت نشده است" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>زمان</th><th>مشتری</th><th>سرویس</th><th>کد ملی / همراه</th><th>نتیجه</th><th>مبلغ</th><th>فاکتور</th><th>پیگیری</th></tr></thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr wire:key="usage-{{ $record->id }}">
                                <td class="text-xs text-gray-600">{{ Jalali::dateTime($record->created_at) }}</td>
                                <td>
                                    <div class="text-gray-800">{{ $record->customer?->display_name }}</div>
                                    <a href="{{ route('licenses.show', $record->license_id) }}" wire:navigate class="ltr-value text-xs text-gray-500 hover:text-brand-700">{{ $record->license?->key_id }}</a>
                                </td>
                                <td>{{ $record->service?->name }}</td>
                                <td class="text-xs leading-5">
                                    @if ($record->national_code || $record->mobile)
                                        <div class="ltr-value text-gray-800">{{ $record->national_code ?? '—' }}</div>
                                        <div class="ltr-value text-gray-500">{{ $record->mobile ?? '—' }}</div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td>
                                    <x-badge :color="$record->status->color()">{{ $record->status->label() }}</x-badge>
                                    @if ($record->upstream_code !== null)
                                        @php($upstreamMessage = $record->upstream_message ?? $codes->map($record->upstream_code)['message'])
                                        <div class="mt-1 max-w-56 truncate text-xs text-gray-500" title="{{ $upstreamMessage }}">{{ $record->upstream_code }} · {{ $upstreamMessage }}</div>
                                    @endif
                                </td>
                                <td>{{ $record->billable ? Money::format($record->unit_price) : 'رایگان' }}</td>
                                <td>
                                    @if ($record->invoice)
                                        <a href="{{ route('invoices.show', $record->invoice) }}" wire:navigate class="link ltr-value">{{ $record->invoice->number }}</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="text-xs text-gray-500"><span class="ltr-value">{{ $record->reference ?? '—' }}</span>@if ($record->duration_ms)<div>{{ number_format($record->duration_ms) }}ms</div>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $records->links() }}
        @endif
    </x-card>
</div>
