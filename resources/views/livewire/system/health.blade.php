@php
    $icons = ['ok' => 'heroicon-s-check-circle', 'warn' => 'heroicon-s-exclamation-triangle', 'fail' => 'heroicon-s-x-circle'];
    $colors = ['ok' => 'text-emerald-500', 'warn' => 'text-amber-500', 'fail' => 'text-red-500'];
    $problems = collect($checks)->where('status', '!=', 'ok')->count();
@endphp

<div>
    <x-page-header title="سلامت سامانه" description="تنظیمات هاست و برنامه که روی سرعت API اثر دارند." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card :title="$problems === 0 ? 'همه موارد درست است' : $problems.' مورد نیاز به بررسی دارد'" :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($checks as $check)
                        <li class="flex gap-3 px-5 py-3.5 text-sm">
                            <x-dynamic-component :component="$icons[$check['status']]" @class(['mt-0.5 size-5 shrink-0', $colors[$check['status']]]) />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                    <span class="font-medium text-gray-800">{{ $check['label'] }}</span>
                                    <span class="text-gray-500">{{ $check['value'] }}</span>
                                </div>
                                @if ($check['hint'])
                                    <p class="mt-1 text-xs leading-6 text-gray-500">{{ $check['hint'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            @if ($tables)
                <x-card title="بزرگ‌ترین جدول‌ها" description="تعداد ردیف‌ها تقریبی است (آمار MySQL)." :padding="false">
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead><tr><th>جدول</th><th>ردیف‌ها</th><th>حجم</th></tr></thead>
                            <tbody>
                                @foreach ($tables as $table)
                                    <tr>
                                        <td class="ltr-value">{{ $table['table'] }}</td>
                                        <td>{{ number_format($table['rows']) }}</td>
                                        <td>{{ $table['size_mb'] }} MB</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="استعلام شاهکار">
                @if ($circuit['open'])
                    <div class="flex items-start gap-3 text-sm">
                        <x-heroicon-s-pause-circle class="mt-0.5 size-5 shrink-0 text-amber-500" />
                        <div>
                            <p class="font-medium text-gray-800">موقتاً متوقف است</p>
                            <p class="mt-1 text-xs leading-6 text-gray-500">سیتاد چند بار پشت سر هم پاسخ نداد. تا {{ max(1, $circuit['until'] - time()) }} ثانیه دیگر درخواست‌ها بدون انتظار خطا می‌گیرند تا پردازش‌های هاست پر نشود.</p>
                        </div>
                    </div>
                    <x-button size="sm" variant="secondary" class="mt-4" wire:click="resetCircuit" wire:target="resetCircuit">فعال‌سازی فوری</x-button>
                @else
                    <div class="flex items-start gap-3 text-sm">
                        <x-heroicon-s-check-circle class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                        <div>
                            <p class="font-medium text-gray-800">فعال</p>
                            <p class="mt-1 text-xs leading-6 text-gray-500">هر استعلام حداکثر {{ config('license.shahkar.deadline_seconds') }} ثانیه طول می‌کشد. بعد از {{ config('license.shahkar.circuit_threshold') }} خطای ارتباط در یک دقیقه، استعلام‌ها {{ config('license.shahkar.circuit_cooldown_seconds') }} ثانیه متوقف می‌شوند.</p>
                        </div>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</div>
