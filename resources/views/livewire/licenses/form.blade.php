<div>
    <x-page-header :title="$license ? 'ویرایش لایسنس' : 'صدور لایسنس جدید'" :description="$license?->key_id">
        <x-slot:breadcrumb><a href="{{ route('licenses.index') }}" wire:navigate class="hover:text-gray-700">لایسنس‌ها</a></x-slot:breadcrumb>
    </x-page-header>

    <form wire:submit="save" class="space-y-6">
        <x-card title="مشتری و محصول">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="customer_id" label="مشتری" :options="$customers" placeholder="انتخاب مشتری" required />
                <x-select name="product_id" label="محصول" :options="$products" placeholder="انتخاب محصول" required />
                <x-input name="label" label="برچسب" hint="مثلاً «سرور اصلی» یا «شعبه تهران»؛ برای تشخیص چند لایسنس یک مشتری." />
                <x-input name="app_url" label="آدرس اجرای سامانه" placeholder="https://fishino.example.com" hint="آدرسی که نرم‌افزار مشتری روی آن اجرا می‌شود؛ فقط https و بدون /api. اپ موبایل با کد لایسنس به همین آدرس وصل می‌شود." ltr />
            </div>
        </x-card>

        <x-card title="اعتبار">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-input name="starts_at" label="تاریخ شروع" ltr placeholder="1405/01/01" hint="خالی = از همین حالا" />
                <x-input name="expires_at" label="تاریخ انقضا" ltr placeholder="1405/12/29" hint="تا پایان همین روز معتبر است. خالی = نامحدود" />
                <x-select name="status" label="وضعیت" :options="$statuses" />
                <x-input name="max_users" label="حداکثر کاربر" type="number" min="1" ltr hint="خالی = نامحدود. برنامه مشتری آن را اعمال می‌کند." />
            </div>
        </x-card>

        <x-card title="محدودیت دسترسی" description="خالی بگذارید تا محدودیتی اعمال نشود.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-textarea name="allowed_domains" label="دامنه‌های مجاز" rows="4" ltr placeholder="crm.example.com&#10;*.example.com" hint="هر دامنه در یک خط. *.example.com همه زیردامنه‌ها را شامل می‌شود." />
                <x-textarea name="allowed_ips" label="IPهای مجاز سرور مشتری" rows="4" ltr placeholder="185.1.2.3&#10;10.0.0.0/8" hint="هر IP یا محدوده CIDR در یک خط. محکم‌ترین محدودیت است." />
            </div>
        </x-card>

        <x-card title="قابلیت‌ها و سرویس‌ها">
            <div class="grid gap-6 sm:grid-cols-2">
                <x-textarea name="features" label="قابلیت‌های فعال (Feature flags)" rows="5" ltr placeholder="reports=true&#10;max_branches=3&#10;plan=pro" hint="هر قابلیت در یک خط به شکل key=value. در پاسخ امضاشده به برنامه ارسال می‌شود." />
                <div>
                    <span class="mb-2 block text-sm font-medium text-gray-700">سرویس‌های پولی مجاز</span>
                    <div class="space-y-2">
                        @foreach ($availableServices as $service)
                            <label class="flex items-start gap-2 rounded-lg border border-gray-200 p-3 text-sm hover:bg-gray-50">
                                <input type="checkbox" value="{{ $service->id }}" wire:model="services" class="mt-0.5 size-4 rounded border-gray-300 text-brand-600 focus:ring-brand-200">
                                <span>
                                    <span class="font-medium text-gray-800">{{ $service->name }}</span>
                                    <span class="block text-xs text-gray-500">تعرفه پیش‌فرض: {{ \App\Support\Money::format($service->unit_price) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-card>

        <x-card>
            <x-textarea name="notes" label="یادداشت داخلی" rows="2" />
        </x-card>

        <div class="flex items-center justify-end gap-2">
            <x-button variant="secondary" :href="$license ? route('licenses.show', $license) : route('licenses.index')" wire:navigate>انصراف</x-button>
            <x-button type="submit" wire:target="save">{{ $license ? 'ذخیره تغییرات' : 'صدور لایسنس' }}</x-button>
        </div>
    </form>
</div>
