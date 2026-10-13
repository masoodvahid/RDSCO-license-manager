@php use App\Support\Jalali; @endphp

<div>
    <x-page-header :title="$error->exception_class ? class_basename($error->exception_class) : 'خطا'">
        <x-slot:breadcrumb><a href="{{ route('errors.index') }}" wire:navigate class="hover:text-gray-700">خطاهای سامانه‌ها</a></x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$error->status->color()">{{ $error->status->label() }}</x-badge>
            @if ($error->status === \App\Enums\ErrorStatus::Open)
                <x-button wire:click="setStatus('resolved')" icon="heroicon-o-check">حل شد</x-button>
                <x-button variant="secondary" wire:click="setStatus('ignored')">نادیده گرفتن</x-button>
            @else
                <x-button variant="secondary" wire:click="setStatus('open')">بازگشایی</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="پیام">
                <p class="text-sm leading-7 whitespace-pre-wrap break-words text-gray-900" dir="auto">{{ $error->message }}</p>
                @if ($error->file)
                    <p class="mt-3 text-xs text-gray-500"><span class="ltr-value">{{ $error->file }}{{ $error->line ? ':'.$error->line : '' }}</span></p>
                @endif
                @if ($error->exception_class)
                    <p class="mt-1 text-xs text-gray-500"><span class="ltr-value">{{ $error->exception_class }}</span></p>
                @endif
            </x-card>

            @if ($error->trace)
                <x-card title="Stack trace (آخرین وقوع)" :padding="false">
                    <pre class="max-h-[32rem] overflow-auto bg-gray-950 p-4 text-left text-xs leading-5 text-gray-100" dir="ltr">{{ $error->trace }}</pre>
                </x-card>
            @endif

            @if ($error->context)
                <x-card title="Context (آخرین وقوع)" :padding="false">
                    <pre class="max-h-96 overflow-auto bg-gray-50 p-4 text-left text-xs leading-5 text-gray-700" dir="ltr">{{ json_encode($error->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="جزئیات">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">تعداد وقوع</dt><dd class="font-semibold">{{ number_format($error->occurrences) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">اولین بار</dt><dd>{{ Jalali::dateTime($error->first_seen_at) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">آخرین بار</dt><dd>{{ Jalali::dateTime($error->last_seen_at) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">سطح</dt><dd class="ltr-value">{{ $error->level }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">محصول</dt><dd>{{ $error->product?->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">مشتری</dt><dd><a href="{{ route('customers.show', $error->customer_id) }}" wire:navigate class="link">{{ $error->customer?->display_name }}</a></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">لایسنس</dt><dd><a href="{{ route('licenses.show', $error->license_id) }}" wire:navigate class="link ltr-value">{{ $error->license?->key_id }}</a></dd></div>
                    @if ($error->environment)<div class="flex justify-between gap-4"><dt class="text-gray-500">محیط</dt><dd class="ltr-value">{{ $error->environment }}</dd></div>@endif
                    @if ($error->app_version)<div class="flex justify-between gap-4"><dt class="text-gray-500">نسخه برنامه</dt><dd class="ltr-value">{{ $error->app_version }}</dd></div>@endif
                    @if ($error->url)<div><dt class="text-gray-500">آدرس</dt><dd class="ltr-value mt-1 break-all text-xs">{{ $error->url }}</dd></div>@endif
                </dl>
            </x-card>

            <x-card>
                <x-button variant="ghost" size="sm" class="text-red-600" wire:click="delete" wire:confirm="این گروه خطا حذف شود؟">حذف</x-button>
            </x-card>
        </div>
    </div>
</div>
