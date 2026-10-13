@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'type' => 'button',
])

@php
    $classes = \Illuminate\Support\Arr::toCssClasses([
        'inline-flex items-center justify-center gap-1.5 rounded-lg font-medium whitespace-nowrap transition focus:outline-none focus-visible:ring-3 disabled:cursor-not-allowed disabled:opacity-60',
        'px-2.5 py-1.5 text-xs' => $size === 'sm',
        'px-3.5 py-2 text-sm' => $size === 'md',
        'bg-brand-600 text-white shadow-xs hover:bg-brand-700 focus-visible:ring-brand-200' => $variant === 'primary',
        'border border-gray-300 bg-white text-gray-700 shadow-xs hover:bg-gray-50 focus-visible:ring-gray-200' => $variant === 'secondary',
        'bg-red-600 text-white shadow-xs hover:bg-red-700 focus-visible:ring-red-200' => $variant === 'danger',
        'text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus-visible:ring-gray-200' => $variant === 'ghost',
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-dynamic-component :component="$icon" @class(['size-4' => $size === 'md', 'size-3.5' => $size === 'sm']) />@endif
        {{ $slot }}
    </a>
@else
    @php($target = $attributes->get('wire:click') ?? $attributes->get('wire:target'))
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($target)
            <svg wire:loading wire:target="{{ $target }}" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".25" stroke-width="4"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>
        @endif
        @if ($icon)
            @if ($target)
                <span wire:loading.remove wire:target="{{ $target }}" class="inline-flex"><x-dynamic-component :component="$icon" @class(['size-4' => $size === 'md', 'size-3.5' => $size === 'sm']) /></span>
            @else
                <x-dynamic-component :component="$icon" @class(['size-4' => $size === 'md', 'size-3.5' => $size === 'sm']) />
            @endif
        @endif
        {{ $slot }}
    </button>
@endif
