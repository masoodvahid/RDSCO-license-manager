@php
    use App\Support\Jalali;
    use App\Support\Money;
@endphp

<div>
    <x-page-header title="داشبورد" description="وضعیت کلی لایسنس‌ها، مصرف سرویس‌ها و حساب مشتری‌ها" />

    @if ($twoFactorDisabled)
        <div class="mb-6 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-2">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 size-5 shrink-0" />
                <span>ورود دومرحله‌ای برای حساب شما فعال نیست. این پنل به همه لایسنس‌ها دسترسی دارد؛ لطفاً آن را فعال کنید.</span>
            </div>
            <x-button variant="secondary" size="sm" :href="route('profile')">فعال‌سازی</x-button>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="مشتری فعال" :value="number_format($stats['customers'])" icon="heroicon-o-user-group" :href="route('customers.index')" />
        <x-stat label="لایسنس معتبر" :value="number_format($stats['licenses'])" :hint="$stats['expiring'] > 0 ? number_format($stats['expiring']).' لایسنس تا ۳۰ روز آینده منقضی می‌شود' : null" icon="heroicon-o-key" :href="route('licenses.index')" />
        <x-stat label="درخواست سرویس‌ها در این ماه" :value="number_format($stats['monthRequests'])" :hint="'امروز: '.number_format($stats['todayRequests'])" icon="heroicon-o-chart-bar" :href="route('usage.index')" />
        <x-stat label="خطای باز سامانه‌ها" :value="number_format($stats['openErrors'])" icon="heroicon-o-bug-ant" :href="route('errors.index')" />
        <x-stat label="مصرف فاکتورنشده" :value="Money::format($stats['uninvoiced'])" icon="heroicon-o-receipt-percent" :href="route('invoices.create')" class="col-span-2" />
        <x-stat label="مانده مطالبات" :value="Money::format(max(0, $stats['receivable']))" icon="heroicon-o-banknotes" :href="route('payments.index')" class="col-span-2" />
    </div>

    <x-card title="۳۰ روز اخیر" description="درخواست‌های سرویس‌های پولی بر اساس نتیجه" class="mt-6">
        @php($max = max(1, collect($chart)->max(fn ($day) => $day['success'] + $day['failed'] + $day['error'])))
        <div class="flex h-48 items-end gap-1" dir="ltr">
            @foreach ($chart as $day)
                @php($total = $day['success'] + $day['failed'] + $day['error'])
                <div class="group relative flex h-full flex-1 flex-col justify-end">
                    <div class="flex w-full flex-col-reverse overflow-hidden rounded-t-sm" style="height: {{ max($total > 0 ? 2 : 0, round($total / $max * 100)) }}%">
                        <div class="bg-brand-500" style="flex: {{ $day['success'] }}"></div>
                        <div class="bg-amber-400" style="flex: {{ $day['failed'] }}"></div>
                        <div class="bg-red-400" style="flex: {{ $day['error'] }}"></div>
                    </div>
                    @if ($total === 0)
                        <div class="h-px w-full bg-gray-200"></div>
                    @endif
                    <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs text-white shadow group-hover:block" dir="rtl">
                        <div class="font-medium">{{ Jalali::date($day['date']) }}</div>
                        <div>موفق: {{ $day['success'] }} · عدم تطابق: {{ $day['failed'] }} · خطا: {{ $day['error'] }}</div>
                        <div class="text-gray-300">بررسی لایسنس: {{ number_format($day['verifications']) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500">
            <div class="flex items-center gap-4">
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-brand-500"></span>تطابق</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-amber-400"></span>عدم تطابق</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-red-400"></span>خطا</span>
            </div>
            <span>{{ Jalali::date($chart[0]['date']) }} تا {{ Jalali::date(end($chart)['date']) }}</span>
        </div>
    </x-card>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card title="لایسنس‌های در آستانه انقضا" :padding="false">
            @forelse ($expiringLicenses as $license)
                <a href="{{ route('licenses.show', $license) }}" wire:navigate class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 text-sm last:border-0 hover:bg-gray-50">
                    <div class="min-w-0">
                        <div class="truncate font-medium text-gray-800">{{ $license->customer?->display_name }}</div>
                        <div class="truncate text-xs text-gray-500">{{ $license->product?->name }} {{ $license->label ? '· '.$license->label : '' }}</div>
                    </div>
                    <x-badge :color="$license->daysUntilExpiry() <= 7 ? 'danger' : 'warning'">
                        {{ $license->daysUntilExpiry() === 0 ? 'امروز' : $license->daysUntilExpiry().' روز' }}
                    </x-badge>
                </a>
            @empty
                <x-empty icon="heroicon-o-check-circle" title="هیچ لایسنسی تا ۳۰ روز آینده منقضی نمی‌شود" />
            @endforelse
        </x-card>

        <x-card title="بیشترین مصرف این ماه" :padding="false">
            @forelse ($topCustomers as $row)
                <a href="{{ $row->customer ? route('customers.show', $row->customer) : '#' }}" wire:navigate class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 text-sm last:border-0 hover:bg-gray-50">
                    <span class="truncate font-medium text-gray-800">{{ $row->customer?->display_name ?? '—' }}</span>
                    <span class="flex shrink-0 items-center gap-3 text-xs text-gray-500">
                        <span>{{ number_format($row->requests) }} درخواست</span>
                        <span class="font-medium text-gray-700">{{ Money::format((int) $row->amount) }}</span>
                    </span>
                </a>
            @empty
                <x-empty icon="heroicon-o-chart-bar" title="هنوز مصرفی در این ماه ثبت نشده است" />
            @endforelse
        </x-card>
    </div>

    <x-card title="آخرین خطاهای باز" :padding="false" class="mt-6">
        <x-slot:actions>
            <a href="{{ route('errors.index') }}" wire:navigate class="link text-xs">همه خطاها</a>
        </x-slot:actions>
        @forelse ($recentErrors as $error)
            <a href="{{ route('errors.show', $error) }}" wire:navigate class="block border-b border-gray-100 px-5 py-3 text-sm last:border-0 hover:bg-gray-50">
                <div class="flex items-center justify-between gap-3">
                    <span class="truncate font-medium text-gray-800" dir="auto">{{ \Illuminate\Support\Str::limit($error->message, 120) }}</span>
                    <x-badge color="danger">{{ number_format($error->occurrences) }}×</x-badge>
                </div>
                <div class="mt-1 text-xs text-gray-500">{{ $error->product?->name }} · {{ $error->customer?->display_name }} · {{ Jalali::relative($error->last_seen_at) }}</div>
            </a>
        @empty
            <x-empty icon="heroicon-o-face-smile" title="خطای بازی وجود ندارد" />
        @endforelse
    </x-card>
</div>
