<form wire:submit="login" class="space-y-4">
    <x-input name="email" label="ایمیل" type="email" ltr autocomplete="username" autofocus required />
    <x-input name="password" label="رمز عبور" type="password" ltr autocomplete="current-password" required />

    <label class="flex items-center gap-2 text-sm text-gray-600">
        <input type="checkbox" wire:model="remember" class="size-4 rounded border-gray-300 text-brand-600 focus:ring-brand-200">
        مرا به خاطر بسپار
    </label>

    <x-button type="submit" class="w-full" wire:target="login">ورود</x-button>
</form>
