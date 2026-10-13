<div>
    <x-page-header title="مشتری‌ها" description="اشخاص و شرکت‌هایی که از نرم‌افزارها و سرویس‌ها استفاده می‌کنند">
        <x-slot:actions>
            <x-button :href="route('customers.create')" icon="heroicon-o-plus" wire:navigate>مشتری جدید</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row">
            <div class="relative flex-1">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="جست‌وجو در نام، شرکت، موبایل، کد ملی یا ایمیل" class="field pr-9">
            </div>
            <select wire:model.live="status" class="field sm:w-40">
                <option value="">همه وضعیت‌ها</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($customers->isEmpty())
            <x-empty icon="heroicon-o-user-group" title="مشتری‌ای پیدا نشد" :description="$search !== '' ? 'عبارت جست‌وجو را تغییر دهید.' : 'اولین مشتری را ثبت کنید.'" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>مشتری</th>
                            <th>تماس</th>
                            <th>لایسنس</th>
                            <th>مانده حساب</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            @php($balance = (int) $customer->invoiced_total - (int) $customer->paid_total)
                            <tr wire:key="customer-{{ $customer->id }}">
                                <td>
                                    <a href="{{ route('customers.show', $customer) }}" wire:navigate class="font-medium text-gray-900 hover:text-brand-700">{{ $customer->display_name }}</a>
                                    @if ($customer->company_name)
                                        <div class="text-xs text-gray-500">{{ $customer->name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="ltr-value text-gray-700">{{ $customer->mobile ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ $customer->email }}</div>
                                </td>
                                <td>{{ number_format($customer->licenses_count) }}</td>
                                <td @class(['font-medium', 'text-red-600' => $balance > 0, 'text-emerald-700' => $balance < 0])>
                                    {{ \App\Support\Money::format(abs($balance)) }}
                                    @if ($balance < 0)<span class="text-xs font-normal">بستانکار</span>@endif
                                </td>
                                <td><x-badge :color="$customer->status->color()">{{ $customer->status->label() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $customers->links() }}
        @endif
    </x-card>
</div>
