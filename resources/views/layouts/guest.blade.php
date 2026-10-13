<!DOCTYPE html>
<html lang="fa" dir="rtl" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' | ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full items-center justify-center px-4 py-12 font-sans text-gray-900">
    <div class="w-full max-w-sm">
        <div class="mb-8 flex flex-col items-center gap-3 text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-brand-600 text-white">
                <x-heroicon-o-shield-check class="size-6" />
            </span>
            <div>
                <h1 class="text-lg font-semibold">مدیریت لایسنس راهکار دیجیتال</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $subtitle ?? 'ورود به پنل مدیریت' }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
