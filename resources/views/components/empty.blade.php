@props(['icon' => 'heroicon-o-inbox', 'title' => 'موردی یافت نشد', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center px-6 py-12 text-center') }}>
    <span class="flex size-11 items-center justify-center rounded-full bg-gray-100 text-gray-400">
        <x-dynamic-component :component="$icon" class="size-5" />
    </span>
    <p class="mt-3 text-sm font-medium text-gray-700">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
