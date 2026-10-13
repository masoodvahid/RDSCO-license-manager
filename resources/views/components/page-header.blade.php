@props(['title', 'description' => null])

<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        @isset($breadcrumb)
            <div class="mb-1 text-xs text-gray-500">{{ $breadcrumb }}</div>
        @endisset
        <h1 class="truncate text-xl font-semibold text-gray-900">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
