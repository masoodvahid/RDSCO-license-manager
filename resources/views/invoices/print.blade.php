@php
    use App\Support\Jalali;
    use App\Support\Money;
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>فاکتور {{ $invoice->number }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 14mm; }
        @media print { .no-print { display: none !important; } body { background: #fff; } }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-900">
    <div class="no-print mx-auto flex max-w-3xl justify-end gap-2 px-4 pt-6">
        <button onclick="window.print()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white">چاپ / ذخیره PDF</button>
    </div>

    <main class="mx-auto my-6 max-w-3xl bg-white p-10 shadow-sm print:my-0 print:p-0 print:shadow-none">
        <header class="flex items-start justify-between border-b border-gray-200 pb-6">
            <div>
                <h1 class="text-xl font-bold">صورت‌حساب</h1>
                <div class="mt-2 text-sm text-gray-600">شماره: <span class="ltr-value">{{ $invoice->number }}</span></div>
                <div class="text-sm text-gray-600">تاریخ: {{ Jalali::date($invoice->issued_at) }}</div>
                @if ($invoice->status === \App\Enums\InvoiceStatus::Void)
                    <div class="mt-2 inline-block rounded border border-red-300 px-2 py-0.5 text-sm font-semibold text-red-600">باطل‌شده</div>
                @endif
            </div>
            <div class="text-left text-sm leading-6 text-gray-700" dir="rtl">
                <div class="font-semibold text-gray-900">{{ $company['name'] ?: config('app.name') }}</div>
                @if ($company['national_id'])<div>شناسه ملی: {{ $company['national_id'] }}</div>@endif
                @if ($company['economic_code'])<div>کد اقتصادی: {{ $company['economic_code'] }}</div>@endif
                @if ($company['address'])<div>{{ $company['address'] }}</div>@endif
                @if ($company['phone'])<div>تلفن: <span class="ltr-value">{{ $company['phone'] }}</span></div>@endif
            </div>
        </header>

        <section class="mt-6 rounded-lg bg-gray-50 p-4 text-sm leading-7">
            <div class="font-semibold">خریدار: {{ $invoice->customer?->display_name }}</div>
            @if ($invoice->customer?->company_name)<div>نماینده: {{ $invoice->customer->name }}</div>@endif
            @if ($invoice->customer?->national_id)<div>شناسه/کد ملی: {{ $invoice->customer->national_id }}</div>@endif
            @if ($invoice->customer?->registration_number)<div>شماره ثبت: {{ $invoice->customer->registration_number }}</div>@endif
            @if ($invoice->customer?->address)<div>نشانی: {{ $invoice->customer->address }}@if ($invoice->customer->postal_code) — کد پستی: {{ $invoice->customer->postal_code }}@endif</div>@endif
            <div class="text-gray-600">دوره مصرف: {{ Jalali::date($invoice->period_start) }} تا {{ Jalali::date($invoice->period_end) }}</div>
        </section>

        <table class="mt-6 w-full border-collapse text-sm">
            <thead>
                <tr class="border-b-2 border-gray-300 text-right">
                    <th class="py-2">ردیف</th><th class="py-2">شرح</th><th class="py-2">تعداد</th><th class="py-2">فی (ریال)</th><th class="py-2">مبلغ (ریال)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr class="border-b border-gray-100">
                        <td class="py-2">{{ $loop->iteration }}</td>
                        <td class="py-2">{{ $item->description }}</td>
                        <td class="py-2">{{ number_format($item->quantity) }}</td>
                        <td class="py-2">{{ number_format($item->unit_price) }}</td>
                        <td class="py-2">{{ number_format($item->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <dl class="mr-auto mt-6 w-72 space-y-1.5 text-sm">
            <div class="flex justify-between"><dt>جمع</dt><dd>{{ Money::format($invoice->subtotal) }}</dd></div>
            @if ($invoice->discount > 0)<div class="flex justify-between"><dt>تخفیف</dt><dd>{{ Money::format($invoice->discount) }}</dd></div>@endif
            @if ($invoice->tax_amount > 0)<div class="flex justify-between"><dt>مالیات بر ارزش افزوده ({{ rtrim(rtrim((string) $invoice->tax_rate, '0'), '.') }}٪)</dt><dd>{{ Money::format($invoice->tax_amount) }}</dd></div>@endif
            <div class="flex justify-between border-t-2 border-gray-300 pt-2 text-base font-bold"><dt>مبلغ قابل پرداخت</dt><dd>{{ Money::format($invoice->total) }}</dd></div>
        </dl>

        @if ($invoice->notes || $company['payment_note'])
            <section class="mt-8 border-t border-gray-200 pt-4 text-xs leading-6 text-gray-600 whitespace-pre-line">{{ trim($invoice->notes."\n".$company['payment_note']) }}</section>
        @endif
    </main>
</body>
</html>
