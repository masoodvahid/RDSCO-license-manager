<?php

namespace App\Livewire\System;

use App\Exceptions\UpdateFailed;
use App\Services\Updates\ApplicationUpdater;
use App\Support\Jalali;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Shows installed and available versions. The update itself runs through
 * plain JSON routes (SystemUpdateController) driven by Alpine, because the
 * request after the file swap must not depend on this component's state.
 */
#[Title('بروزرسانی سیستم')]
class Update extends Component
{
    public ?string $currentVersion = null;

    /** @var array{tag: string, name: string, notes_html: string, published_at: string|null, html_url: string|null, package_ready: bool}|null */
    public ?array $latestRelease = null;

    public bool $updateAvailable = false;

    public ?string $releaseError = null;

    public ?string $unavailabilityReason = null;

    /** @var array{stage: string, source: string, version: string}|null */
    public ?array $pendingUpdate = null;

    /** @var list<array{name: string, version: string, created_at: string, size: int}> */
    public array $backups = [];

    public function mount(): void
    {
        $this->loadStatus();
    }

    public function refresh(): void
    {
        $this->loadStatus(fresh: true);
        $this->dispatch('toast', type: 'success', message: $this->updateAvailable ? 'نسخه جدید موجود است.' : 'اطلاعات نسخه‌ها به‌روز شد.');
    }

    public function startUpdate(): void
    {
        if ($this->unavailabilityReason === null && $this->pendingUpdate === null && $this->updateAvailable && ($this->latestRelease['package_ready'] ?? false)) {
            $this->dispatch('system-update-start', steps: ['prepare', 'install', 'finalize']);
        }
    }

    public function startRollback(string $backup): void
    {
        if ($this->unavailabilityReason === null && collect($this->backups)->contains('name', $backup)) {
            $this->dispatch('system-update-start', steps: ['rollback.prepare', 'install', 'finalize'], backup: $backup);
        }
    }

    public function resume(): void
    {
        $stage = $this->pendingUpdate['stage'] ?? null;

        if ($stage !== null) {
            $this->dispatch('system-update-start', steps: $stage === ApplicationUpdater::STAGE_INSTALLED ? ['finalize'] : ['install', 'finalize']);
        }
    }

    public function discard(): void
    {
        $this->dispatch('system-update-start', steps: ['discard']);
    }

    private function loadStatus(bool $fresh = false): void
    {
        $updater = app(ApplicationUpdater::class);

        $this->currentVersion = $updater->currentVersion();
        $this->unavailabilityReason = $updater->unavailabilityReason();
        $this->backups = $this->unavailabilityReason === null ? $updater->backups() : [];
        $this->releaseError = null;
        $this->latestRelease = null;
        $this->updateAvailable = false;

        // Never expose the maintenance bypass secret to the browser.
        $state = $updater->state();
        $this->pendingUpdate = $state === null ? null : [
            'stage' => (string) ($state['stage'] ?? ''),
            'source' => (string) ($state['source'] ?? ''),
            'version' => (string) ($state['version'] ?? ''),
        ];

        try {
            $release = $updater->latestRelease($fresh);
        } catch (UpdateFailed $exception) {
            $this->releaseError = $exception->getMessage();

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->releaseError = 'دریافت اطلاعات آخرین نسخه ناموفق بود.';

            return;
        }

        if ($release === null) {
            $this->releaseError = 'هیچ نسخه منتشرشده‌ای در GitHub پیدا نشد. اگر مخزن خصوصی است، UPDATER_GITHUB_TOKEN را در فایل .env تنظیم کنید.';

            return;
        }

        $this->latestRelease = [
            'tag' => $release['tag'],
            'name' => $release['name'],
            'notes_html' => (string) Str::markdown($release['notes'], ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'published_at' => $release['published_at'] ? Jalali::dateTime(Carbon::parse($release['published_at'])) : null,
            'html_url' => $release['html_url'],
            'package_ready' => $release['package'] !== null && $release['checksum_url'] !== null,
        ];

        $this->updateAvailable = $updater->isNewerThanCurrent($release['tag']);
    }

    public function formatBackupDate(string $timestamp): string
    {
        $date = Carbon::createFromFormat('Ymd-His', $timestamp);

        return $date === null ? $timestamp : (new Verta($date))->format('Y/m/d H:i');
    }

    public function render()
    {
        return view('livewire.system.update');
    }
}
