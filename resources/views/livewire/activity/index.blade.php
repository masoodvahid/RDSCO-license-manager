@php use App\Support\Jalali; @endphp

<div>
    <x-page-header title="گزارش فعالیت" description="هر تغییر مهم در پنل با زمان و IP ثبت می‌شود" />

    <x-card :padding="false">
        <div class="border-b border-gray-100 p-4">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="جست‌وجو در شرح" class="field sm:w-80">
        </div>
        @if ($logs->isEmpty())
            <x-empty icon="heroicon-o-clock" title="فعالیتی ثبت نشده است" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>زمان</th><th>کاربر</th><th>شرح</th><th>IP</th></tr></thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr wire:key="log-{{ $log->id }}">
                                <td class="text-xs text-gray-600">{{ Jalali::dateTime($log->created_at) }}</td>
                                <td>{{ $log->user?->name ?? 'سیستم' }}</td>
                                <td class="max-w-xl truncate">{{ $log->description }}</td>
                                <td><span class="ltr-value text-xs text-gray-500">{{ $log->ip }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $logs->links() }}
        @endif
    </x-card>
</div>
