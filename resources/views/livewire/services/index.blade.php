<div>
    <x-page-header title="تعرفه‌ها" description="قیمت هر درخواست موفق. تعرفه اختصاصی هر مشتری از صفحه همان مشتری تنظیم می‌شود." />

    <form wire:submit="save">
        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>سرویس</th><th>کد</th><th>تعرفه پیش‌فرض (ریال)</th><th>لایسنس‌های مجاز</th><th>تعرفه اختصاصی</th><th>فعال</th></tr></thead>
                    <tbody>
                        @foreach ($services as $service)
                            <tr wire:key="service-{{ $service->id }}">
                                <td><input type="text" wire:model="form.{{ $service->id }}.name" class="field min-w-48"></td>
                                <td><span class="ltr-value text-gray-600">{{ $service->code }}</span></td>
                                <td><input type="text" inputmode="numeric" dir="ltr" wire:model="form.{{ $service->id }}.unit_price" class="field w-40 text-left"></td>
                                <td>{{ number_format($service->licenses_count) }}</td>
                                <td>{{ number_format($service->customer_prices_count) }} مشتری</td>
                                <td><input type="checkbox" wire:model="form.{{ $service->id }}.is_active" class="size-4 rounded border-gray-300 text-brand-600 focus:ring-brand-200"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="mt-4 flex items-center justify-between gap-4">
            <p class="text-xs text-gray-500">فقط پاسخ‌های قطعی (تطابق یا عدم تطابق) شارژ می‌شوند؛ خطاهای فنی رایگان است.</p>
            <x-button type="submit" wire:target="save">ذخیره تعرفه‌ها</x-button>
        </div>
    </form>
</div>
