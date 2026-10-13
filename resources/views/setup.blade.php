<x-layouts.guest-plain subtitle="نصب اولیه">
    <form method="POST" action="{{ route('setup.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        @if ($needsMigration)
            <p class="rounded-lg bg-brand-50 p-3 text-xs text-brand-800">جدول‌های دیتابیس هنوز ساخته نشده‌اند و با ثبت این فرم ساخته می‌شوند.</p>
        @endif

        @foreach ([['name', 'نام', 'text'], ['email', 'ایمیل ورود', 'email'], ['password', 'رمز عبور', 'password'], ['password_confirmation', 'تکرار رمز عبور', 'password']] as [$field, $label, $type])
            <div>
                <label for="{{ $field }}" class="mb-1.5 block text-sm font-medium text-gray-700">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($field) }}" @if ($type !== 'text') dir="ltr" @endif class="field @error($field) field-error @enderror" required>
                @error($field)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @endforeach

        <button type="submit" class="w-full rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-brand-700">ساخت حساب مدیر</button>
    </form>
</x-layouts.guest-plain>
