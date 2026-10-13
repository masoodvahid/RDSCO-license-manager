@php
    use App\Support\Jalali;
    use App\Support\Money;
    $expired = $license->isExpired();
    $daysLeft = $license->daysUntilExpiry();
@endphp

<div>
    <x-page-header :title="$license->customer?->display_name.' · '.$license->product?->name" :description="$license->label">
        <x-slot:breadcrumb><a href="{{ route('licenses.index') }}" wire:navigate class="hover:text-gray-700">لایسنس‌ها</a></x-slot:breadcrumb>
        <x-slot:actions>
            @if ($expired)
                <x-badge color="danger">منقضی</x-badge>
            @else
                <x-badge :color="$license->status->color()">{{ $license->status->label() }}</x-badge>
            @endif
            <x-button variant="secondary" :href="route('licenses.edit', $license)" icon="heroicon-o-pencil-square" wire:navigate>ویرایش</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($secret)
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5">
            <div class="flex items-start gap-3">
                <x-heroicon-o-key class="mt-0.5 size-5 shrink-0 text-amber-600" />
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-semibold text-amber-900">کلید محرمانه (فقط همین یک بار نمایش داده می‌شود)</h3>
                    <p class="mt-1 text-xs text-amber-800">این مقدار را در تنظیمات برنامه مشتری قرار دهید. بعد از ترک این صفحه دیگر قابل مشاهده نیست؛ در صورت گم شدن باید کلید جدید بسازید.</p>
                    <div class="mt-3 rounded-lg border border-amber-200 bg-white px-3 py-2">
                        <x-copy :value="$secret" />
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="اتصال برنامه" description="مقادیر زیر را در فایل .env برنامه مشتری قرار دهید (SDK لاراول).">
                <div class="space-y-2 rounded-lg bg-gray-950 p-4 text-[13px] leading-6 text-gray-100" dir="ltr">
                    <div><span class="text-gray-400">RDSCO_LICENSE_URL=</span>{{ rtrim(config('app.url'), '/') }}</div>
                    <div class="flex items-center gap-2"><span class="text-gray-400">RDSCO_LICENSE_KEY_ID=</span><x-copy :value="$license->key_id" class="text-gray-100" /></div>
                    <div><span class="text-gray-400">RDSCO_LICENSE_SECRET=</span>{{ $secret ?? '•••••• (فقط هنگام صدور یا تعویض نمایش داده می‌شود)' }}</div>
                    <div class="flex items-center gap-2"><span class="text-gray-400">RDSCO_LICENSE_PUBLIC_KEY=</span><x-copy :value="$publicKey" class="text-gray-100" /></div>
                </div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500">
                    <span>
                        @if ($license->secret_rotated_at)
                            آخرین تعویض کلید: {{ Jalali::dateTime($license->secret_rotated_at) }}
                        @else
                            کلید محرمانه از زمان صدور تغییر نکرده است.
                        @endif
                    </span>
                    <x-button variant="secondary" size="sm" icon="heroicon-o-arrow-path" wire:click="rotateSecret" wire:confirm="کلید محرمانه جدید ساخته شود؟ برنامه مشتری تا به‌روزرسانی تنظیماتش قطع می‌شود.">تعویض کلید محرمانه</x-button>
                </div>
            </x-card>

            <x-card title="بررسی‌های لایسنس در ۳۰ روز اخیر">
                @php($max = max(1, collect($days)->max(fn ($day) => $day['verifications'] + $day['rejections'])))
                <div class="flex h-28 items-end gap-1" dir="ltr">
                    @foreach ($days as $day)
                        @php($total = $day['verifications'] + $day['rejections'])
                        <div class="group relative flex h-full flex-1 flex-col justify-end" title="{{ Jalali::date($day['date']) }}: {{ $day['verifications'] }} موفق، {{ $day['rejections'] }} رد">
                            <div class="flex w-full flex-col-reverse overflow-hidden rounded-t-sm" style="height: {{ $total > 0 ? max(3, round($total / $max * 100)) : 0 }}%">
                                <div class="bg-brand-500" style="flex: {{ $day['verifications'] }}"></div>
                                <div class="bg-red-400" style="flex: {{ $day['rejections'] }}"></div>
                            </div>
                            @if ($total === 0)<div class="h-px w-full bg-gray-200"></div>@endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500">
                    <span>آخرین اتصال: {{ $license->last_seen_at ? Jalali::dateTime($license->last_seen_at) : 'هرگز' }}</span>
                    @if ($license->last_seen_ip)<span>IP: <span class="ltr-value">{{ $license->last_seen_ip }}</span></span>@endif
                    @if ($license->last_seen_version)<span>نسخه برنامه: <span class="ltr-value">{{ $license->last_seen_version }}</span></span>@endif
                </div>
            </x-card>

            <x-card title="مصرف سرویس‌ها (کل دوره)">
                <div class="grid grid-cols-3 gap-4 text-center">
                    @foreach (['success' => 'تطابق', 'failed' => 'عدم تطابق', 'error' => 'خطا'] as $status => $label)
                        <div class="rounded-lg bg-gray-50 p-3">
                            <div class="text-xs text-gray-500">{{ $label }}</div>
                            <div class="mt-1 text-lg font-semibold">{{ number_format($usage[$status]->total ?? 0) }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex items-center justify-between text-sm">
                    <span class="text-gray-500">مبلغ قابل شارژ</span>
                    <span class="font-medium">{{ Money::format(collect($usage)->sum('amount')) }}</span>
                </div>
                <a href="{{ route('usage.index', ['license' => $license->id]) }}" wire:navigate class="link mt-3 inline-block text-xs">جزئیات درخواست‌ها</a>
            </x-card>

            <x-card title="آخرین خطاهای گزارش‌شده" :padding="false">
                @forelse ($recentErrors as $error)
                    <a href="{{ route('errors.show', $error) }}" wire:navigate class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 text-sm last:border-0 hover:bg-gray-50">
                        <span class="truncate text-gray-800" dir="auto">{{ \Illuminate\Support\Str::limit($error->message, 100) }}</span>
                        <span class="flex shrink-0 items-center gap-2">
                            <x-badge :color="$error->status->color()">{{ $error->status->label() }}</x-badge>
                            <span class="text-xs text-gray-500">{{ number_format($error->occurrences) }}×</span>
                        </span>
                    </a>
                @empty
                    <x-empty icon="heroicon-o-face-smile" title="خطایی گزارش نشده است" />
                @endforelse
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="مشخصات">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">مشتری</dt><dd><a href="{{ route('customers.show', $license->customer) }}" wire:navigate class="link">{{ $license->customer?->display_name }}</a></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">محصول</dt><dd class="text-gray-800">{{ $license->product?->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">شروع</dt><dd class="text-gray-800">{{ $license->starts_at ? Jalali::date($license->starts_at) : '—' }}</dd></div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">انقضا</dt>
                        <dd class="text-gray-800">
                            {{ $license->expires_at ? Jalali::date($license->expires_at) : 'نامحدود' }}
                            @if ($daysLeft !== null && $daysLeft >= 0)<span class="text-xs text-gray-500">({{ $daysLeft }} روز)</span>@endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">حداکثر کاربر</dt><dd class="text-gray-800">{{ $license->max_users ? number_format($license->max_users) : 'نامحدود' }}</dd></div>
                    <div>
                        <dt class="text-gray-500">دامنه‌های مجاز</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            @forelse ($license->allowed_domains ?? [] as $domain)
                                <span class="ltr-value rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $domain }}</span>
                            @empty
                                <span class="text-gray-400">بدون محدودیت</span>
                            @endforelse
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">IPهای مجاز</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            @forelse ($license->allowed_ips ?? [] as $ip)
                                <span class="ltr-value rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $ip }}</span>
                            @empty
                                <span class="text-gray-400">بدون محدودیت</span>
                            @endforelse
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">قابلیت‌ها</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            @forelse ($license->features ?? [] as $key => $value)
                                <span class="ltr-value rounded bg-brand-50 px-1.5 py-0.5 text-xs text-brand-700">{{ $key }}={{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}</span>
                            @empty
                                <span class="text-gray-400">—</span>
                            @endforelse
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">سرویس‌های پولی</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            @forelse ($license->services as $service)
                                <x-badge color="info">{{ $service->name }}</x-badge>
                            @empty
                                <span class="text-gray-400">هیچ</span>
                            @endforelse
                        </dd>
                    </div>
                    @if ($license->notes)
                        <div class="rounded-lg bg-gray-50 p-3 text-xs whitespace-pre-line text-gray-600">{{ $license->notes }}</div>
                    @endif
                </dl>
            </x-card>

            <x-card title="عملیات">
                <div class="space-y-4">
                    <div>
                        <p class="mb-2 text-xs text-gray-500">تمدید از تاریخ انقضای فعلی</p>
                        <div class="flex flex-wrap gap-2">
                            <x-button variant="secondary" size="sm" wire:click="extend(1)" wire:confirm="لایسنس یک ماه تمدید شود؟">۱ ماه</x-button>
                            <x-button variant="secondary" size="sm" wire:click="extend(6)" wire:confirm="لایسنس شش ماه تمدید شود؟">۶ ماه</x-button>
                            <x-button variant="secondary" size="sm" wire:click="extend(12)" wire:confirm="لایسنس یک سال تمدید شود؟">۱ سال</x-button>
                        </div>
                    </div>
                    <div>
                        <p class="mb-2 text-xs text-gray-500">وضعیت (بلافاصله اعمال می‌شود)</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($statuses as $status)
                                @if ($status !== $license->status)
                                    <x-button :variant="$status === \App\Enums\LicenseStatus::Revoked ? 'danger' : 'secondary'" size="sm" wire:click="setStatus('{{ $status->value }}')" wire:confirm="وضعیت لایسنس «{{ $status->label() }}» شود؟">{{ $status->label() }}</x-button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <x-button variant="ghost" size="sm" class="text-red-600" wire:click="delete" wire:confirm="لایسنس برای همیشه حذف شود؟">حذف لایسنس</x-button>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</div>
