<form wire:submit="verify" class="space-y-4">
    @if ($useRecoveryCode)
        <p class="text-sm text-gray-600">یکی از کدهای بازیابی خود را وارد کنید. هر کد فقط یک بار قابل استفاده است.</p>
        <x-input name="code" label="کد بازیابی" ltr autocomplete="off" autofocus placeholder="XXXXX-XXXXX" />
    @else
        <p class="text-sm text-gray-600">کد ۶ رقمی برنامه احراز هویت (Google Authenticator) را وارد کنید.</p>
        <x-input name="code" label="کد احراز هویت" ltr inputmode="numeric" autocomplete="one-time-code" autofocus maxlength="6" class="tracking-widest" />
    @endif

    <x-button type="submit" class="w-full" wire:target="verify">تأیید و ورود</x-button>

    <div class="flex items-center justify-between text-sm">
        <button type="button" wire:click="toggleRecovery" class="link">
            {{ $useRecoveryCode ? 'استفاده از کد برنامه' : 'استفاده از کد بازیابی' }}
        </button>
        <a href="{{ route('login') }}" class="text-gray-500 hover:text-gray-700">بازگشت</a>
    </div>
</form>
