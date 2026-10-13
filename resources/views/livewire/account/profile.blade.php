<div>
    <x-page-header title="حساب کاربری" />

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <x-card title="مشخصات">
                <form wire:submit="saveProfile" class="space-y-4">
                    <x-input name="name" label="نام" required />
                    <x-input name="email" label="ایمیل ورود" type="email" ltr required />
                    <div class="flex justify-end"><x-button type="submit" wire:target="saveProfile">ذخیره</x-button></div>
                </form>
            </x-card>

            <x-card title="تغییر رمز عبور">
                <form wire:submit="savePassword" class="space-y-4">
                    <x-input name="current_password" label="رمز فعلی" type="password" ltr autocomplete="current-password" />
                    <x-input name="password" label="رمز جدید" type="password" ltr autocomplete="new-password" hint="حداقل ۱۰ نویسه، شامل حرف و عدد." />
                    <x-input name="password_confirmation" label="تکرار رمز جدید" type="password" ltr autocomplete="new-password" />
                    <div class="flex justify-end"><x-button type="submit" wire:target="savePassword">تغییر رمز</x-button></div>
                </form>
            </x-card>
        </div>

        <x-card title="ورود دومرحله‌ای (Google Authenticator)">
            @if ($recoveryCodes)
                <div class="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <p class="text-sm font-medium text-amber-900">کدهای بازیابی (فقط همین یک بار نمایش داده می‌شوند)</p>
                    <p class="mt-1 text-xs text-amber-800">اگر گوشی را گم کنید، با هر کدام از این کدها یک بار می‌توانید وارد شوید.</p>
                    <div class="mt-3 grid grid-cols-2 gap-2" dir="ltr">
                        @foreach ($recoveryCodes as $code)
                            <span class="rounded bg-white px-2 py-1 text-center font-mono text-sm">{{ $code }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($enabled)
                <div class="flex items-center gap-2 text-sm text-emerald-700">
                    <x-heroicon-o-shield-check class="size-5" />
                    ورود دومرحله‌ای فعال است.
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ $remainingCodes }} کد بازیابی استفاده‌نشده باقی مانده است.</p>

                <div class="mt-5 space-y-4 border-t border-gray-100 pt-5">
                    <x-button variant="secondary" size="sm" wire:click="regenerateRecoveryCodes" wire:confirm="کدهای بازیابی قبلی باطل و کدهای جدید ساخته شوند؟">ساخت کدهای بازیابی جدید</x-button>

                    <form wire:submit="disableTwoFactor" class="flex items-end gap-2">
                        <x-input name="disablePassword" label="برای غیرفعال‌سازی رمز عبور را وارد کنید" type="password" ltr class="flex-1" />
                        <x-button type="submit" variant="danger" size="md" wire:target="disableTwoFactor">غیرفعال‌سازی</x-button>
                    </form>
                </div>
            @elseif ($pendingSecret)
                <ol class="list-inside list-decimal space-y-2 text-sm text-gray-700">
                    <li>برنامه Google Authenticator یا مشابه را باز کنید.</li>
                    <li>کد QR زیر را اسکن کنید یا کلید را دستی وارد کنید.</li>
                    <li>کد ۶ رقمی نمایش‌داده‌شده را وارد و تأیید کنید.</li>
                </ol>
                <div class="mt-4 flex flex-col items-center gap-3">
                    <div class="w-48 rounded-lg border border-gray-200 bg-white p-2 [&_svg]:h-auto [&_svg]:w-full">{!! $qrCode !!}</div>
                    <x-copy :value="$pendingSecret" class="text-xs" />
                </div>
                <form wire:submit="confirmTwoFactor" class="mt-5 flex items-end gap-2">
                    <x-input name="confirmationCode" label="کد ۶ رقمی" ltr inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="flex-1" />
                    <x-button type="submit" wire:target="confirmTwoFactor">تأیید و فعال‌سازی</x-button>
                </form>
            @else
                <p class="text-sm text-gray-600">با فعال‌سازی، ورود به پنل علاوه بر رمز عبور به کد برنامه احراز هویت گوشی نیاز دارد. این پنل به همه لایسنس‌ها و اطلاعات مالی دسترسی دارد؛ فعال‌سازی به‌شدت توصیه می‌شود.</p>
                <x-button class="mt-4" icon="heroicon-o-shield-check" wire:click="startTwoFactor">فعال‌سازی</x-button>
            @endif
        </x-card>
    </div>
</div>
