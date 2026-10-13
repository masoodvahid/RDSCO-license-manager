<div>
    <x-page-header title="تنظیمات" />
    @include('livewire.settings.tabs')

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="space-y-6 lg:col-span-2">
            <x-card title="اتصال سیتاد و شاهکار" description="مقادیر را از قرارداد سیتاد و سازمان تنظیم مقررات وارد کنید. فیلدهای محرمانه رمزنگاری می‌شوند و نمایش داده نمی‌شوند.">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($fields as $key => [$label, $isSecret])
                        @php($field = str_replace('.', '_', $key))
                        @if ($key === 'shahkar.public_key')
                            <x-textarea name="values.{{ $field }}" :label="$label" rows="5" ltr placeholder="-----BEGIN PUBLIC KEY-----" class="sm:col-span-2" hint="محتوای فایل pem کلید عمومی شاهکار (EC)." />
                        @elseif ($isSecret)
                            <x-input name="values.{{ $field }}" :label="$label" type="password" ltr autocomplete="new-password" :placeholder="$configured[$field] ? '•••••••• (ذخیره شده؛ برای تغییر مقدار جدید وارد کنید)' : ''" />
                        @else
                            <x-input name="values.{{ $field }}" :label="$label" ltr :class="str_ends_with($key, '_url') ? 'sm:col-span-2' : ''" />
                        @endif
                    @endforeach
                </div>
            </x-card>
            <div class="flex justify-end"><x-button type="submit" wire:target="save">ذخیره تنظیمات</x-button></div>
        </form>

        <div class="space-y-6">
            <x-card title="وضعیت">
                @if ($missing === [])
                    <div class="flex items-center gap-2 text-sm text-emerald-700"><x-heroicon-o-check-circle class="size-5" />همه تنظیمات لازم وارد شده است.</div>
                @else
                    <p class="mb-2 text-sm text-amber-700">این موارد هنوز تنظیم نشده‌اند:</p>
                    <ul class="list-inside list-disc space-y-1 text-xs text-gray-600">
                        @foreach ($missing as $label)<li>{{ $label }}</li>@endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="آزمایش اتصال" description="استعلام آزمایشی در دفتر مصرف ثبت نمی‌شود، اما ممکن است سیتاد آن را محاسبه کند.">
                <div class="space-y-3">
                    <x-button variant="secondary" size="sm" class="w-full" icon="heroicon-o-signal" wire:click="testToken">آزمایش دریافت توکن سیتاد</x-button>
                    <div class="border-t border-gray-100 pt-3">
                        <x-input name="testNationalCode" label="کد ملی آزمایشی" ltr inputmode="numeric" />
                        <x-input name="testMobile" label="شماره همراه آزمایشی" ltr class="mt-3" />
                        <x-button size="sm" class="mt-3 w-full" icon="heroicon-o-shield-check" wire:click="testVerification">استعلام آزمایشی</x-button>
                    </div>
                </div>

                @if ($testResult)
                    <div @class(['mt-4 rounded-lg border p-3 text-sm', 'border-emerald-200 bg-emerald-50 text-emerald-800' => $testResult['ok'], 'border-red-200 bg-red-50 text-red-800' => ! $testResult['ok']])>
                        <div class="font-medium">{{ $testResult['title'] }}</div>
                        @if (! empty($testResult['details']))
                            <dl class="mt-2 space-y-1 text-xs">
                                @foreach ($testResult['details'] as $label => $value)
                                    <div class="flex justify-between gap-3"><dt class="opacity-75">{{ $label }}</dt><dd class="ltr-value break-all text-left">{{ $value }}</dd></div>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</div>
