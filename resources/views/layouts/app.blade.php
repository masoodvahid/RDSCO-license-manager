<!DOCTYPE html>
<html lang="fa" dir="rtl" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' | ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-gray-900" x-data="{ sidebarOpen: false }">
    @php
        $navigation = [
            ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'داشبورد', 'icon' => 'heroicon-o-squares-2x2'],
            ['route' => 'products.index', 'match' => 'products.*', 'label' => 'محصولات', 'icon' => 'heroicon-o-cube'],
            ['route' => 'customers.index', 'match' => 'customers.*', 'label' => 'مشتری‌ها', 'icon' => 'heroicon-o-user-group'],
            ['route' => 'licenses.index', 'match' => 'licenses.*', 'label' => 'لایسنس‌ها', 'icon' => 'heroicon-o-key'],
            ['divider' => 'مالی'],
            ['route' => 'services.index', 'match' => 'services.*', 'label' => 'تعرفه‌ها', 'icon' => 'heroicon-o-tag'],
            ['route' => 'usage.index', 'match' => 'usage.*', 'label' => 'مصرف سرویس‌ها', 'icon' => 'heroicon-o-chart-bar'],
            ['route' => 'payments.index', 'match' => 'payments.*', 'label' => 'پرداخت‌ها', 'icon' => 'heroicon-o-banknotes'],
            ['route' => 'invoices.index', 'match' => 'invoices.*', 'label' => 'فاکتورها', 'icon' => 'heroicon-o-document-text'],
            ['divider' => 'پایش'],
            ['route' => 'errors.index', 'match' => 'errors.*', 'label' => 'خطاهای سامانه‌ها', 'icon' => 'heroicon-o-bug-ant', 'badge' => \App\Models\AppError::query()->where('status', 'open')->count()],
            ['route' => 'activity.index', 'match' => 'activity.*', 'label' => 'گزارش فعالیت', 'icon' => 'heroicon-o-clock'],
            ['divider' => 'سیستم'],
            ['route' => 'settings.general', 'match' => 'settings.*', 'label' => 'تنظیمات', 'icon' => 'heroicon-o-cog-6-tooth'],
            ['route' => 'system.update', 'match' => 'system.update', 'label' => 'بروزرسانی', 'icon' => 'heroicon-o-arrow-path'],
            ['route' => 'system.health', 'match' => 'system.health', 'label' => 'سلامت سامانه', 'icon' => 'heroicon-o-heart'],
            ['route' => 'docs', 'match' => 'docs', 'label' => 'مستندات API', 'icon' => 'heroicon-o-book-open'],
        ];
    @endphp

    {{-- Mobile backdrop --}}
    <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden" @click="sidebarOpen = false"></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 right-0 z-40 flex w-64 translate-x-full flex-col border-l border-gray-200 bg-white transition-transform duration-200 lg:translate-x-0"
        :class="sidebarOpen && '!translate-x-0'"
    >
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-gray-100 px-5">
            <span class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-white">
                <x-heroicon-o-shield-check class="size-5" />
            </span>
            <div class="leading-tight">
                <div class="text-sm font-semibold">مدیریت لایسنس</div>
                <div class="text-xs text-gray-500">راهکار دیجیتال</div>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4">
            <ul class="space-y-0.5">
                @foreach ($navigation as $item)
                    @if (isset($item['divider']))
                        <li class="px-3 pb-1 pt-5 text-xs font-medium text-gray-400">{{ $item['divider'] }}</li>
                    @else
                        @php($isActive = request()->routeIs($item['match']))
                        <li>
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                @class([
                                    'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition',
                                    'bg-brand-50 font-medium text-brand-700' => $isActive,
                                    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => ! $isActive,
                                ])
                            >
                                <x-dynamic-component :component="$item['icon']" @class(['size-5 shrink-0', 'text-brand-600' => $isActive, 'text-gray-400 group-hover:text-gray-500' => ! $isActive]) />
                                <span class="flex-1">{{ $item['label'] }}</span>
                                @if (($item['badge'] ?? 0) > 0)
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <div class="border-t border-gray-100 p-3">
            <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
                <span class="flex size-8 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">
                    {{ mb_substr(auth()->user()->name, 0, 1) }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-medium text-gray-800">{{ auth()->user()->name }}</span>
                    <span class="block truncate text-xs text-gray-400">{{ auth()->user()->email }}</span>
                </span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-50 hover:text-gray-800">
                    <x-heroicon-o-arrow-right-start-on-rectangle class="size-5 text-gray-400" />
                    خروج
                </button>
            </form>
            <a href="{{ route('system.update') }}" wire:navigate class="mt-2 block px-3 text-xs text-gray-400 hover:text-gray-600" title="بروزرسانی">
                {{ \App\Support\AppVersion::label() }}
            </a>
        </div>
    </aside>

    <div class="lg:pr-64">
        {{-- Mobile top bar --}}
        <div class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-gray-200 bg-white/90 px-4 backdrop-blur lg:hidden">
            <button type="button" class="-m-2 p-2 text-gray-600" @click="sidebarOpen = true" aria-label="باز کردن منو">
                <x-heroicon-o-bars-3 class="size-6" />
            </button>
            <span class="text-sm font-semibold">مدیریت لایسنس</span>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            {{ $slot }}
        </main>
    </div>

    <x-toasts />
</body>
</html>
