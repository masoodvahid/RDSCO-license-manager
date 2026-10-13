<div
    x-data="{
        urls: @js([
            'prepare' => route('system-update.prepare'),
            'rollback.prepare' => route('system-update.rollback.prepare'),
            'install' => route('system-update.install'),
            'finalize' => route('system-update.finalize'),
            'discard' => route('system-update.discard'),
        ]),
        labels: {
            'prepare': 'دانلود، بررسی checksum و تهیه نسخه پشتیبان',
            'rollback.prepare': 'آماده‌سازی نسخه پشتیبان و تهیه پشتیبان از وضعیت فعلی',
            'install': 'فعال‌سازی Maintenance و جایگزینی فایل‌ها',
            'finalize': 'اجرای Migration، بازسازی کش و خروج از Maintenance',
            'discard': 'لغو بروزرسانی آماده‌شده',
        },
        csrfToken: @js(csrf_token()),
        steps: [],
        running: false,
        finished: false,
        failed: false,
        async start(detail) {
            if (this.running) return;

            this.steps = (detail.steps ?? []).map((name) => ({ name, label: this.labels[name] ?? name, status: 'pending', message: '' }));
            this.running = true;
            this.finished = false;
            this.failed = false;

            for (const step of this.steps) {
                step.status = 'running';

                try {
                    const response = await fetch(this.urls[step.name], {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(step.name === 'rollback.prepare' ? { backup: detail.backup } : {}),
                    });

                    let data = {};
                    try { data = await response.json(); } catch (error) {}

                    if (! response.ok || data.ok !== true) {
                        step.status = 'failed';
                        step.message = data.message ?? `خطای HTTP ${response.status}`;
                        this.failed = true;
                        break;
                    }

                    step.status = 'done';
                    step.message = data.message ?? '';
                } catch (error) {
                    step.status = 'failed';
                    step.message = 'ارتباط با سرور قطع شد. صفحه را دوباره بارگذاری کنید تا وضعیت بروزرسانی مشخص شود.';
                    this.failed = true;
                    break;
                }
            }

            this.running = false;
            this.finished = ! this.failed;

            if (this.finished) {
                setTimeout(() => window.location.reload(), 1500);
            }
        },
    }"
    x-on:system-update-start.window="start($event.detail)"
    x-on:beforeunload.window="if (running) { $event.preventDefault(); $event.returnValue = ''; }"
>
    <x-page-header title="بروزرسانی سیستم" description="نصب آخرین نسخه منتشرشده در GitHub، با نسخه پشتیبان و امکان بازگشت">
        <x-slot:actions>
            <x-button variant="secondary" icon="heroicon-o-arrow-path" wire:click="refresh">بررسی نسخه جدید</x-button>
            @if ($unavailabilityReason === null && $pendingUpdate === null && $updateAvailable && ($latestRelease['package_ready'] ?? false))
                <x-button icon="heroicon-o-cloud-arrow-down" wire:click="startUpdate" wire:confirm="ابتدا از فایل‌های فعلی نسخه پشتیبان گرفته می‌شود. در زمان جایگزینی فایل‌ها و اجرای Migration، سایت و API برای چند ثانیه تا چند دقیقه در حالت Maintenance قرار می‌گیرند. ادامه می‌دهید؟">
                    نصب نسخه {{ $latestRelease['tag'] }}
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-6">
        @if ($unavailabilityReason)
            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 size-5 shrink-0" />
                <div><div class="font-medium">بروزرسانی داخلی در این نصب فعال نیست</div><div class="mt-1">{{ $unavailabilityReason }}</div></div>
            </div>
        @endif

        @if ($pendingUpdate)
            @php($installed = $pendingUpdate['stage'] === \App\Services\Updates\ApplicationUpdater::STAGE_INSTALLED)
            <div @class(['flex flex-col gap-3 rounded-xl border p-4 text-sm sm:flex-row sm:items-center sm:justify-between', 'border-red-200 bg-red-50 text-red-800' => $installed, 'border-brand-200 bg-brand-50 text-brand-800' => ! $installed])>
                <div>
                    <div class="font-medium">
                        {{ $installed ? 'فایل‌های نسخه '.$pendingUpdate['version'].' نصب شده‌اند اما مرحله نهایی کامل نشده است' : 'نسخه '.$pendingUpdate['version'].' آماده نصب است' }}
                    </div>
                    <div class="mt-1 text-xs">{{ $installed ? 'سایت در حالت Maintenance است. مرحله نهایی را دوباره اجرا کنید یا از فهرست پشتیبان‌ها به نسخه قبل برگردید.' : 'می‌توانید نصب را ادامه دهید یا آن را لغو کنید.' }}</div>
                </div>
                <div class="flex gap-2">
                    <x-button size="sm" wire:click="resume" wire:confirm="ادامه داده شود؟">{{ $installed ? 'اجرای دوباره مرحله نهایی' : 'ادامه نصب' }}</x-button>
                    @unless ($installed)
                        <x-button variant="secondary" size="sm" wire:click="discard" wire:confirm="بروزرسانی آماده‌شده لغو شود؟">لغو</x-button>
                    @endunless
                </div>
            </div>
        @endif

        <template x-if="steps.length > 0">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs">
                <h2 class="mb-4 text-sm font-semibold">پیشرفت عملیات</h2>
                <ol class="space-y-3">
                    <template x-for="step in steps" :key="step.name">
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center">
                                <svg x-show="step.status === 'running'" class="size-5 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".25" stroke-width="4"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>
                                <x-heroicon-s-check-circle x-show="step.status === 'done'" class="size-5 text-emerald-500" />
                                <x-heroicon-s-x-circle x-show="step.status === 'failed'" class="size-5 text-red-500" />
                                <x-heroicon-o-clock x-show="step.status === 'pending'" class="size-5 text-gray-300" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-900" x-text="step.label"></span>
                                <span class="mt-1 block text-sm" :class="step.status === 'failed' ? 'text-red-600' : 'text-gray-600'" x-show="step.message" x-text="step.message"></span>
                            </span>
                        </li>
                    </template>
                </ol>
                <p x-show="running" class="mt-4 text-sm text-amber-700">تا پایان عملیات این صفحه را نبندید و دوباره بارگذاری نکنید.</p>
                <p x-show="finished" class="mt-4 text-sm text-emerald-700">عملیات کامل شد. صفحه به‌صورت خودکار دوباره بارگذاری می‌شود…</p>
                <div x-show="failed" class="mt-4">
                    <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm" x-on:click="window.location.reload()">بارگذاری دوباره صفحه</button>
                </div>
            </section>
        </template>

        <x-card title="نسخه‌ها">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-gray-500">نسخه نصب‌شده</dt>
                    <dd class="mt-1 text-base font-semibold"><span class="ltr-value">{{ $currentVersion ?? 'نامشخص' }}</span></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">آخرین نسخه منتشرشده در GitHub</dt>
                    <dd class="mt-1 flex flex-wrap items-center gap-2">
                        @if ($latestRelease)
                            <span class="ltr-value text-base font-semibold">{{ $latestRelease['tag'] }}</span>
                            @if ($updateAvailable && $latestRelease['package_ready'])
                                <x-badge color="success">نسخه جدید آماده نصب</x-badge>
                            @elseif ($updateAvailable)
                                <x-badge color="warning">فایل‌های بسته هنوز آماده نیست</x-badge>
                            @else
                                <x-badge>سیستم به‌روز است</x-badge>
                            @endif
                        @else
                            <span class="text-sm text-gray-400">—</span>
                        @endif
                    </dd>
                    @if ($latestRelease && $latestRelease['published_at'])
                        <p class="mt-1 text-xs text-gray-500">انتشار: {{ $latestRelease['published_at'] }}</p>
                    @endif
                </div>
            </dl>
            @if ($releaseError)
                <p class="mt-4 text-sm text-red-600">{{ $releaseError }}</p>
            @endif
        </x-card>

        @if ($latestRelease && trim(strip_tags($latestRelease['notes_html'])) !== '')
            <x-card :title="'تغییرات '.$latestRelease['name']">
                <div class="space-y-2 text-sm leading-7 text-gray-700 [&_h1]:font-semibold [&_h2]:font-semibold [&_h3]:font-semibold [&_li]:mr-5 [&_ul]:list-disc">{!! $latestRelease['notes_html'] !!}</div>
                @if ($latestRelease['html_url'])
                    <a href="{{ $latestRelease['html_url'] }}" target="_blank" rel="noopener noreferrer" class="link mt-3 inline-block text-xs">مشاهده در GitHub</a>
                @endif
            </x-card>
        @endif

        <x-card title="نسخه‌های پشتیبان" description="قبل از هر بروزرسانی یا بازگردانی، از فایل‌های برنامه پشتیبان گرفته می‌شود. فایل .env، پوشه storage و دیتابیس جزو پشتیبان نیستند." :padding="false">
            @forelse ($backups as $backup)
                <div wire:key="backup-{{ $backup['name'] }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 last:border-0">
                    <div class="text-sm">
                        <span class="ltr-value font-medium">{{ $backup['version'] }}</span>
                        <span class="mr-2 text-xs text-gray-500">{{ $this->formatBackupDate($backup['created_at']) }} — {{ number_format($backup['size'] / 1048576, 1) }} مگابایت</span>
                    </div>
                    <x-button variant="secondary" size="sm" icon="heroicon-o-arrow-uturn-left" wire:click="startRollback('{{ $backup['name'] }}')" wire:confirm="فایل‌های برنامه با این نسخه پشتیبان جایگزین شوند؟ تغییرات دیتابیس برگردانده نمی‌شوند.">بازگردانی</x-button>
                </div>
            @empty
                <x-empty icon="heroicon-o-archive-box" title="هنوز نسخه پشتیبانی وجود ندارد" />
            @endforelse
        </x-card>
    </div>
</div>
