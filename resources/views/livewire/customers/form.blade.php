<div>
    <x-page-header :title="$customer ? 'ویرایش '.$customer->display_name : 'مشتری جدید'">
        <x-slot:breadcrumb><a href="{{ route('customers.index') }}" wire:navigate class="hover:text-gray-700">مشتری‌ها</a></x-slot:breadcrumb>
    </x-page-header>

    <form wire:submit="save" class="space-y-6">
        <x-card title="مشخصات">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="name" label="نام و نام خانوادگی / نام رابط" required />
                <x-input name="company_name" label="نام شرکت" hint="در صورت وجود، در فاکتور و فهرست‌ها نمایش داده می‌شود." />
                <x-input name="national_id" label="کد ملی / شناسه ملی" ltr />
                <x-input name="economic_code" label="کد اقتصادی" ltr />
                <x-input name="mobile" label="موبایل" ltr />
                <x-input name="email" label="ایمیل" type="email" ltr />
                <x-input name="address" label="نشانی" class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card title="وضعیت و یادداشت">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="status" label="وضعیت" :options="$statuses" hint="با تعلیق مشتری، همه لایسنس‌های او از کار می‌افتند." />
                <x-textarea name="notes" label="یادداشت داخلی" class="sm:col-span-2" />
            </div>
        </x-card>

        <div class="flex items-center justify-end gap-2">
            <x-button variant="secondary" :href="$customer ? route('customers.show', $customer) : route('customers.index')" wire:navigate>انصراف</x-button>
            <x-button type="submit" wire:target="save">ذخیره</x-button>
        </div>
    </form>
</div>
