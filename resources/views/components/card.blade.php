@props(['title' => null, 'description' => null, 'padding' => true])

<section {{ $attributes->class('overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs') }}>
    @if ($title || isset($actions))
        <header class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-3.5">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-sm font-semibold text-gray-900">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-xs text-gray-500">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
