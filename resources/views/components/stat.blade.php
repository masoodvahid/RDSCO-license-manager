@props(['label', 'value', 'hint' => null, 'icon' => null, 'href' => null])

@php($tag = $href ? 'a' : 'div')

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->class(['block rounded-xl border border-gray-200 bg-white p-5 shadow-xs', 'transition hover:border-gray-300' => $href]) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm text-gray-500">{{ $label }}</p>
        @if ($icon)
            <x-dynamic-component :component="$icon" class="size-5 text-gray-300" />
        @endif
    </div>
    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-900">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>
