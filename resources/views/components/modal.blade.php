{{-- Usage: <x-modal wire:model="showForm" title="..."> ... <x-slot:footer>...</x-slot:footer></x-modal> --}}
@props(['title' => null, 'maxWidth' => 'lg'])

<div
    x-data="{ open: @entangle($attributes->wire('model')) }"
    x-cloak
    x-show="open"
    x-on:keydown.escape.window="open = false"
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-900/40" @click="open = false"></div>

    <div class="relative flex min-h-full items-start justify-center p-4 sm:items-center">
        <div
            x-show="open"
            x-transition
            @class([
                'relative w-full rounded-2xl bg-white shadow-xl',
                'max-w-md' => $maxWidth === 'md',
                'max-w-lg' => $maxWidth === 'lg',
                'max-w-2xl' => $maxWidth === '2xl',
            ])
        >
            @if ($title)
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="open = false" aria-label="بستن">
                        <x-heroicon-o-x-mark class="size-5" />
                    </button>
                </div>
            @endif

            <div class="px-5 py-4">{{ $slot }}</div>

            @isset($footer)
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50/60 px-5 py-3 rounded-b-2xl">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</div>
