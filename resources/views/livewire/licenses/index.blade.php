@php use App\Support\Jalali; @endphp

<div>
    <x-page-header title="لایسنس‌ها" description="مجوز استفاده هر مشتری از هر محصول">
        <x-slot:actions>
            <x-button :href="route('licenses.create')" icon="heroicon-o-plus" wire:navigate>لایسنس جدید</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row">
            <div class="relative flex-1">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="شناسه کلید، برچسب یا نام مشتری" class="field pr-9">
            </div>
            <select wire:model.live="product" class="field sm:w-48">
                <option value="">همه محصولات</option>
                @foreach ($products as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model.live="status" class="field sm:w-44">
                <option value="">همه وضعیت‌ها</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($licenses->isEmpty())
            <x-empty icon="heroicon-o-key" title="لایسنسی پیدا نشد" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>مشتری</th><th>محصول</th><th>شناسه کلید</th><th>انقضا</th><th>آخرین اتصال</th><th>وضعیت</th></tr></thead>
                    <tbody>
                        @foreach ($licenses as $license)
                            @php($days = $license->daysUntilExpiry())
                            <tr wire:key="license-{{ $license->id }}">
                                <td>
                                    <a href="{{ route('licenses.show', $license) }}" wire:navigate class="font-medium text-gray-900 hover:text-brand-700">{{ $license->customer?->display_name }}</a>
                                    @if ($license->label)<div class="text-xs text-gray-500">{{ $license->label }}</div>@endif
                                </td>
                                <td>{{ $license->product?->name }}</td>
                                <td><span class="ltr-value text-gray-600">{{ $license->key_id }}</span></td>
                                <td>
                                    @if ($license->expires_at)
                                        <div>{{ Jalali::date($license->expires_at) }}</div>
                                        @if ($days !== null && $days >= 0 && $days <= 30)
                                            <div class="text-xs text-amber-600">{{ $days === 0 ? 'امروز' : $days.' روز مانده' }}</div>
                                        @endif
                                    @else
                                        <span class="text-gray-400">نامحدود</span>
                                    @endif
                                </td>
                                <td class="text-xs text-gray-500">
                                    {{ $license->last_seen_at ? Jalali::relative($license->last_seen_at) : 'هرگز' }}
                                    @if ($license->last_seen_version)<div class="ltr-value">v{{ $license->last_seen_version }}</div>@endif
                                </td>
                                <td>
                                    @if ($license->isExpired())
                                        <x-badge color="danger">منقضی</x-badge>
                                    @else
                                        <x-badge :color="$license->status->color()">{{ $license->status->label() }}</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $licenses->links() }}
        @endif
    </x-card>
</div>
