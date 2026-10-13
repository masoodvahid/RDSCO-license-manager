@php use App\Support\Jalali; @endphp

<div>
    <x-page-header title="خطاهای سامانه‌ها" description="خطاهای گزارش‌شده از نرم‌افزارهای متصل؛ خطاهای یکسان گروه‌بندی و شمرده می‌شوند" />

    <x-card :padding="false">
        <div class="grid gap-3 border-b border-gray-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="جست‌وجو در پیام یا کلاس خطا" class="field">
            <select wire:model.live="status" class="field">
                <option value="">همه وضعیت‌ها</option>
                @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            <select wire:model.live="product" class="field">
                <option value="">همه محصولات</option>
                @foreach ($products as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select wire:model.live="customer" class="field">
                <option value="">همه مشتری‌ها</option>
                @foreach ($customers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
        </div>

        @forelse ($errors_ as $error)
            <div wire:key="error-{{ $error->id }}" class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 last:border-0 sm:flex-row sm:items-center">
                <a href="{{ route('errors.show', $error) }}" wire:navigate class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <x-badge :color="in_array($error->level, ['emergency', 'alert', 'critical']) ? 'danger' : ($error->level === 'warning' ? 'warning' : 'gray')">{{ $error->level }}</x-badge>
                        @if ($error->exception_class)<span class="ltr-value truncate text-xs text-gray-500">{{ class_basename($error->exception_class) }}</span>@endif
                    </div>
                    <p class="mt-1.5 truncate text-sm font-medium text-gray-900" dir="auto">{{ $error->message }}</p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $error->product?->name }} · {{ $error->customer?->display_name }}
                        @if ($error->app_version) · v{{ $error->app_version }} @endif
                        · آخرین بار {{ Jalali::relative($error->last_seen_at) }}
                    </p>
                </a>
                <div class="flex shrink-0 items-center gap-2">
                    <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700" title="تعداد وقوع">{{ number_format($error->occurrences) }}×</span>
                    @if ($error->status === \App\Enums\ErrorStatus::Open)
                        <x-button variant="secondary" size="sm" wire:click="setStatus({{ $error->id }}, 'resolved')">حل شد</x-button>
                        <x-button variant="ghost" size="sm" wire:click="setStatus({{ $error->id }}, 'ignored')">نادیده</x-button>
                    @else
                        <x-badge :color="$error->status->color()">{{ $error->status->label() }}</x-badge>
                        <x-button variant="ghost" size="sm" wire:click="setStatus({{ $error->id }}, 'open')">بازگشایی</x-button>
                    @endif
                </div>
            </div>
        @empty
            <x-empty icon="heroicon-o-face-smile" title="خطایی با این فیلترها وجود ندارد" />
        @endforelse

        {{ $errors_->links() }}
    </x-card>
</div>
