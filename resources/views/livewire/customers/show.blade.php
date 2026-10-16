@php
    use App\Support\Jalali;
    use App\Support\Money;
    $balance = $invoiced - $paid;
@endphp

<div>
    <x-page-header :title="$customer->display_name" :description="$customer->company_name ? $customer->name : null">
        <x-slot:breadcrumb><a href="{{ route('customers.index') }}" wire:navigate class="hover:text-gray-700">مشتری‌ها</a></x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$customer->status->color()">{{ $customer->status->label() }}</x-badge>
            <x-button variant="secondary" :href="route('customers.edit', $customer)" icon="heroicon-o-pencil-square" wire:navigate>ویرایش</x-button>
            <x-button :href="route('licenses.create', ['customer' => $customer->id])" icon="heroicon-o-plus" wire:navigate>لایسنس جدید</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="مصرف فاکتورنشده" :value="Money::format($preview['subtotal'])" :hint="number_format($preview['count']).' درخواست'" />
        <x-stat label="جمع فاکتورها" :value="Money::format($invoiced)" />
        <x-stat label="جمع پرداخت‌ها" :value="Money::format($paid)" />
        <x-stat :label="$balance >= 0 ? 'بدهی' : 'بستانکاری'" :value="Money::format(abs($balance))" :class="$balance > 0 ? 'ring-1 ring-red-100' : ''" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="لایسنس‌ها" :padding="false">
                @if ($licenses->isEmpty())
                    <x-empty icon="heroicon-o-key" title="این مشتری هنوز لایسنسی ندارد">
                        <x-button size="sm" :href="route('licenses.create', ['customer' => $customer->id])" wire:navigate>صدور لایسنس</x-button>
                    </x-empty>
                @else
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead><tr><th>محصول</th><th>شناسه کلید</th><th>انقضا</th><th>آخرین اتصال</th><th>وضعیت</th></tr></thead>
                            <tbody>
                                @foreach ($licenses as $license)
                                    <tr wire:key="license-{{ $license->id }}">
                                        <td>
                                            <a href="{{ route('licenses.show', $license) }}" wire:navigate class="font-medium text-gray-900 hover:text-brand-700">{{ $license->product?->name }}</a>
                                            @if ($license->label)<div class="text-xs text-gray-500">{{ $license->label }}</div>@endif
                                        </td>
                                        <td><span class="ltr-value text-gray-600">{{ $license->key_id }}</span></td>
                                        <td>{{ $license->expires_at ? Jalali::date($license->expires_at) : 'نامحدود' }}</td>
                                        <td class="text-xs text-gray-500">{{ $license->last_seen_at ? Jalali::relative($license->last_seen_at) : 'هرگز' }}</td>
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
                @endif
            </x-card>

            <x-card title="گزارش تماس‌ها" :padding="false">
                <form wire:submit="addContact" class="space-y-3 border-b border-gray-100 p-5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-input name="contactDate" label="تاریخ" placeholder="۱۴۰۵/۰۱/۳۱" ltr />
                        <x-select name="contactChannel" label="روش" :options="$channelOptions" placeholder="—" />
                    </div>
                    <x-textarea name="contactSummary" label="شرح گفتگو" rows="2" />
                    <div class="flex justify-end">
                        <x-button type="submit" size="sm" icon="heroicon-o-plus" wire:target="addContact">ثبت گزارش</x-button>
                    </div>
                </form>
                @forelse ($contacts as $contact)
                    <div wire:key="contact-{{ $contact->id }}" class="group flex gap-4 border-b border-gray-100 px-5 py-3 text-sm last:border-0">
                        <div class="w-24 shrink-0">
                            <div class="font-medium text-gray-800">{{ Jalali::date($contact->contacted_on) }}</div>
                            @if ($contact->channel)<div class="mt-1"><x-badge>{{ $contact->channel->label() }}</x-badge></div>@endif
                        </div>
                        <p class="flex-1 whitespace-pre-line leading-7 text-gray-700">{{ $contact->summary }}</p>
                        <button type="button" wire:click="deleteContact({{ $contact->id }})" wire:confirm="این گزارش حذف شود؟" class="self-start text-gray-300 hover:text-red-600" title="حذف">
                            <x-heroicon-o-trash class="size-4" />
                        </button>
                    </div>
                @empty
                    <x-empty icon="heroicon-o-chat-bubble-left-right" title="هنوز گزارشی ثبت نشده است" />
                @endforelse
            </x-card>

            <x-card title="فاکتورها" :padding="false">
                <x-slot:actions>
                    @if ($preview['count'] > 0)
                        <x-button size="sm" :href="route('invoices.create', ['customer' => $customer->id])" icon="heroicon-o-document-plus" wire:navigate>صدور فاکتور</x-button>
                    @endif
                </x-slot:actions>
                @forelse ($invoices as $invoice)
                    <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 text-sm last:border-0 hover:bg-gray-50">
                        <span class="ltr-value font-medium text-gray-800">{{ $invoice->number }}</span>
                        <span class="text-xs text-gray-500">{{ Jalali::date($invoice->issued_at) }}</span>
                        <span class="font-medium">{{ Money::format($invoice->total) }}</span>
                        <x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge>
                    </a>
                @empty
                    <x-empty icon="heroicon-o-document-text" title="فاکتوری صادر نشده است" />
                @endforelse
            </x-card>

            <x-card title="پرداخت‌ها" :padding="false">
                <x-slot:actions>
                    <x-button size="sm" variant="secondary" :href="route('payments.index', ['customer' => $customer->id, 'create' => 1])" icon="heroicon-o-plus" wire:navigate>ثبت پرداخت</x-button>
                </x-slot:actions>
                @forelse ($payments as $payment)
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 text-sm last:border-0">
                        <span class="text-gray-600">{{ Jalali::date($payment->paid_on) }}</span>
                        <span class="text-xs text-gray-500">{{ $payment->method->label() }} {{ $payment->reference ? '· '.$payment->reference : '' }}</span>
                        <span class="font-medium text-emerald-700">{{ Money::format($payment->amount) }}</span>
                    </div>
                @empty
                    <x-empty icon="heroicon-o-banknotes" title="پرداختی ثبت نشده است" />
                @endforelse
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="اطلاعات تماس">
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        'موبایل' => $customer->mobile,
                        'ایمیل' => $customer->email,
                        'کد ملی / شناسه ملی' => $customer->national_id,
                        'شماره ثبت' => $customer->registration_number,
                        'کد پستی' => $customer->postal_code,
                    ] as $label => $value)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ $label }}</dt>
                            <dd class="ltr-value text-gray-800">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                    @if ($customer->websiteUrl())
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">سایت</dt>
                            <dd><a href="{{ $customer->websiteUrl() }}" target="_blank" rel="noopener noreferrer" class="link ltr-value">{{ preg_replace('#^https?://#i', '', rtrim($customer->website, '/')) }}</a></dd>
                        </div>
                    @endif
                    @if ($customer->channels())
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">روش ارتباط</dt>
                            <dd class="flex flex-wrap justify-end gap-1">
                                @foreach ($customer->channels() as $channel)
                                    <x-badge>{{ $channel->label() }}</x-badge>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if ($customer->address)
                        <div>
                            <dt class="text-gray-500">نشانی</dt>
                            <dd class="mt-1 text-gray-800">{{ $customer->address }}</dd>
                        </div>
                    @endif
                    @if ($customer->notes)
                        <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600 whitespace-pre-line">{{ $customer->notes }}</div>
                    @endif
                </dl>
            </x-card>

            <x-card title="تعرفه اختصاصی" description="خالی بگذارید تا تعرفه پیش‌فرض سرویس اعمال شود.">
                <form wire:submit="savePrices" class="space-y-3">
                    @foreach ($services as $service)
                        <div>
                            <label class="mb-1 block text-sm text-gray-700">{{ $service->name }}</label>
                            <div class="flex items-center gap-2">
                                <input type="text" inputmode="numeric" dir="ltr" wire:model="prices.{{ $service->id }}" placeholder="{{ number_format($service->unit_price) }}" class="field text-left">
                                <span class="shrink-0 text-xs text-gray-500">ریال</span>
                            </div>
                        </div>
                    @endforeach
                    <x-button type="submit" variant="secondary" size="sm" wire:target="savePrices">ذخیره تعرفه‌ها</x-button>
                </form>
            </x-card>

            <x-card title="حذف مشتری">
                <p class="mb-3 text-xs text-gray-500">مشتری به بایگانی می‌رود، سوابق مالی حفظ می‌شود و همه لایسنس‌های او غیرفعال می‌شوند.</p>
                <x-button variant="danger" size="sm" wire:click="delete" wire:confirm="این مشتری حذف و لایسنس‌هایش غیرفعال شوند؟">حذف مشتری</x-button>
            </x-card>
        </div>
    </div>
</div>
