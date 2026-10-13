@props(['value'])

<span x-data="{ copied: false }" {{ $attributes->class('inline-flex items-center gap-1.5') }}>
    <span class="ltr-value break-all">{{ $slot->isEmpty() ? $value : $slot }}</span>
    <button
        type="button"
        class="shrink-0 text-gray-400 hover:text-gray-700"
        title="کپی"
        @click="navigator.clipboard.writeText(@js($value)); copied = true; setTimeout(() => copied = false, 1500)"
    >
        <x-heroicon-o-clipboard-document x-show="! copied" class="size-4" />
        <x-heroicon-o-check x-show="copied" x-cloak class="size-4 text-emerald-600" />
    </button>
</span>
