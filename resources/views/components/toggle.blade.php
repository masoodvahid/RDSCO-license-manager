@props(['label', 'name', 'description' => null])

<label {{ $attributes->class('flex cursor-pointer items-start gap-3') }}>
    <input type="checkbox" wire:model="{{ $name }}" class="peer sr-only">
    <span class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 rounded-full bg-gray-200 transition peer-checked:bg-brand-600 peer-focus-visible:ring-3 peer-focus-visible:ring-brand-100 after:absolute after:top-0.5 after:right-0.5 after:size-4 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:-translate-x-4"></span>
    <span class="text-sm">
        <span class="font-medium text-gray-800">{{ $label }}</span>
        @if ($description)
            <span class="block text-xs text-gray-500">{{ $description }}</span>
        @endif
    </span>
</label>
