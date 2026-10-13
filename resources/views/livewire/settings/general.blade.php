<div>
    <x-page-header title="تنظیمات" />
    @include('livewire.settings.tabs')

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="space-y-6 lg:col-span-2">
            <x-card title="اطلاعات فروشنده در فاکتور">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="settings.company_name" label="نام شرکت" />
                    <x-input name="settings.company_phone" label="تلفن" ltr />
                    <x-input name="settings.company_national_id" label="شناسه ملی" ltr />
                    <x-input name="settings.company_economic_code" label="کد اقتصادی" ltr />
                    <x-input name="settings.company_address" label="نشانی" class="sm:col-span-2" />
                    <x-textarea name="settings.company_payment_note" label="توضیح پرداخت (مثلاً شماره شبا)" rows="2" class="sm:col-span-2" />
                </div>
            </x-card>

            <x-card title="مالیات">
                <x-input name="settings.billing_tax_rate" label="نرخ مالیات بر ارزش افزوده (٪)" ltr inputmode="decimal" hint="روی فاکتورهای جدید اعمال می‌شود. ۰ یا خالی = بدون مالیات." class="sm:w-64" />
            </x-card>

            <div class="flex justify-end">
                <x-button type="submit" wire:target="save">ذخیره</x-button>
            </div>
        </form>

        <div class="space-y-6">
            <x-card title="کلید امضای لایسنس (Ed25519)" description="پاسخ‌های لایسنس با این کلید امضا می‌شوند. برنامه‌ها فقط کلید عمومی را دارند.">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">شناسه کلید</dt><dd class="ltr-value">{{ $keyId }}</dd></div>
                    <div>
                        <dt class="mb-1 text-gray-500">کلید عمومی</dt>
                        <dd class="rounded-lg bg-gray-50 px-3 py-2"><x-copy :value="$publicKey" class="text-xs" /></dd>
                    </div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">مهلت کار آفلاین</dt><dd>{{ $graceHours }} ساعت</dd></div>
                </dl>
                <div class="mt-4 border-t border-gray-100 pt-4">
                    <p class="mb-2 text-xs text-gray-500">فقط اگر کلید خصوصی لو رفته باشد کلید جدید بسازید؛ همه برنامه‌ها تا دریافت کلید عمومی جدید توکن‌ها را رد می‌کنند.</p>
                    <x-button variant="danger" size="sm" wire:click="regenerateSigningKey" wire:confirm="کلید امضای جدید ساخته شود؟ همه برنامه‌ها باید به‌روز شوند.">ساخت کلید جدید</x-button>
                </div>
            </x-card>
        </div>
    </div>
</div>
