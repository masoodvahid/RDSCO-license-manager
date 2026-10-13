<div>
    <x-page-header title="محصولات" description="نرم‌افزارهایی که برای آن‌ها لایسنس صادر می‌شود">
        <x-slot:actions>
            <x-button wire:click="create" icon="heroicon-o-plus">محصول جدید</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        @if ($products->isEmpty())
            <x-empty icon="heroicon-o-cube" title="هنوز محصولی تعریف نشده است" description="برای هر نرم‌افزاری که لایسنس آن را می‌فروشید یک محصول بسازید." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>نام</th><th>شناسه</th><th>لایسنس‌ها</th><th>وضعیت</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr wire:key="product-{{ $product->id }}">
                                <td>
                                    <div class="font-medium text-gray-900">{{ $product->name }}</div>
                                    @if ($product->description)<div class="max-w-md truncate text-xs text-gray-500">{{ $product->description }}</div>@endif
                                </td>
                                <td><span class="ltr-value text-gray-600">{{ $product->slug }}</span></td>
                                <td><a href="{{ route('licenses.index', ['product' => $product->id]) }}" wire:navigate class="link">{{ number_format($product->licenses_count) }}</a></td>
                                <td><x-badge :color="$product->is_active ? 'success' : 'gray'">{{ $product->is_active ? 'فعال' : 'غیرفعال' }}</x-badge></td>
                                <td class="text-left"><x-button variant="ghost" size="sm" wire:click="edit({{ $product->id }})" icon="heroicon-o-pencil-square">ویرایش</x-button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-modal wire:model="showForm" :title="$editingId ? 'ویرایش محصول' : 'محصول جدید'">
        <form id="product-form" wire:submit="save" class="space-y-4">
            <x-input name="name" label="نام محصول" modifier="blur" required />
            <x-input name="slug" label="شناسه (Slug)" ltr hint="در توکن لایسنس و SDK استفاده می‌شود؛ بعد از صدور لایسنس تغییرش ندهید." required />
            <x-textarea name="description" label="توضیح" rows="2" />
            <x-toggle name="is_active" label="فعال" description="با غیرفعال‌کردن، همه لایسنس‌های این محصول از کار می‌افتند." />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">انصراف</x-button>
            <x-button type="submit" form="product-form" wire:target="save">ذخیره</x-button>
        </x-slot:footer>
    </x-modal>
</div>
