@if ($paginator->hasPages())
    <nav role="navigation" aria-label="صفحه‌بندی" class="flex items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 text-sm">
        <p class="text-gray-500">
            {{ number_format($paginator->firstItem()) }} تا {{ number_format($paginator->lastItem()) }} از {{ number_format($paginator->total()) }}
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg px-3 py-1.5 text-gray-300">قبلی</span>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-100">قبلی</button>
            @endif

            <span class="px-2 text-gray-500">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-100">بعدی</button>
            @else
                <span class="rounded-lg px-3 py-1.5 text-gray-300">بعدی</span>
            @endif
        </div>
    </nav>
@endif
