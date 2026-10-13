<div class="mb-6 flex gap-1 border-b border-gray-200">
    @foreach (['settings.general' => 'عمومی و امضا', 'settings.shahkar' => 'اتصال شاهکار'] as $route => $label)
        <a href="{{ route($route) }}" wire:navigate @class([
            '-mb-px border-b-2 px-4 py-2.5 text-sm transition',
            'border-brand-600 font-medium text-brand-700' => request()->routeIs($route),
            'border-transparent text-gray-500 hover:text-gray-800' => ! request()->routeIs($route),
        ])>{{ $label }}</a>
    @endforeach
</div>
