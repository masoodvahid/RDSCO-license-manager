<div>
    <x-page-header :title="$customer ? 'ویرایش '.$customer->display_name : 'مشتری جدید'">
        <x-slot:breadcrumb><a href="{{ route('customers.index') }}" wire:navigate class="hover:text-gray-700">مشتری‌ها</a></x-slot:breadcrumb>
    </x-page-header>

    <form wire:submit="save" class="space-y-6">
        <x-card title="مشخصات">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="type" label="نوع مشتری" :options="$types" live required />
                <div class="hidden sm:block"></div>
                <x-input name="name" :label="$type === 'legal' ? 'نام رابط' : 'نام و نام خانوادگی'" required />
                <x-input name="company_name" label="نام شرکت" hint="در صورت وجود، در فاکتور و فهرست‌ها نمایش داده می‌شود." />
                <x-input name="national_id" :label="$nationalIdLabel" inputmode="numeric" :maxlength="$type === 'legal' ? 11 : 10" ltr wire:key="national-id-{{ $type }}" />
                @if ($type === 'legal')
                    <x-input name="registration_number" label="شماره ثبت" ltr />
                @endif
            </div>
        </x-card>

        <x-card title="اطلاعات تماس">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="mobile" label="موبایل" ltr />
                <x-input name="email" label="ایمیل" type="email" ltr />
                <x-input name="website" label="آدرس سایت اصلی" placeholder="example.com" ltr />
                <x-input name="postal_code" label="کد پستی" inputmode="numeric" maxlength="10" ltr />
                <x-input name="address" label="نشانی" class="sm:col-span-2" />

                <fieldset class="sm:col-span-2">
                    <legend class="mb-1.5 block text-sm font-medium text-gray-700">روش ارتباط</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($channels as $value => $label)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                                <input type="checkbox" value="{{ $value }}" wire:model="contact_channels" class="size-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-gray-500">می‌توانید چند روش را انتخاب کنید.</p>
                    @error('contact_channels.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </fieldset>
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
