<?php

namespace App\Services\Updates;

use App\Exceptions\UpdateFailed;
use App\Models\ActivityLog;
use App\Services\ApplicationOptimizer;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Installs releases from GitHub inside the running application.
 *
 * The update runs in three steps, each in its own HTTP request:
 *
 *  1. prepare  – download, verify, extract to a staging area, back up current files.
 *  2. install  – maintenance mode on, copy staged files over the live ones.
 *  3. finalize – migrate, rebuild caches, maintenance mode off.
 *
 * Splitting install and finalize guarantees migrations and cache rebuilding
 * run in a fresh PHP process that loaded only the new code. Running them in
 * the same request that replaced the files would mix old and new classes.
 */
class ApplicationUpdater
{
    public const STAGE_PREPARED = 'prepared';

    public const STAGE_INSTALLED = 'installed';

    private const LOCK_KEY = 'updater:lock';

    private const MANIFEST_FILE = '.deploy-current-manifest.json';

    private const BACKUP_NAME_PATTERN = '/^(\d{8}-\d{6})__([A-Za-z0-9._-]{1,64})\.zip$/';

    private const VERSION_PATTERN = '/^v?\d+(?:\.\d+){0,3}(?:[-+][0-9A-Za-z.-]+)?$/';

    public function __construct(
        private readonly GitHubReleaseClient $releases,
        private readonly ApplicationOptimizer $optimizer,
    ) {}

    // ---------------------------------------------------------------------
    // Read-only information for the admin page
    // ---------------------------------------------------------------------

    public function currentVersion(): ?string
    {
        $releaseFile = $this->coreDirectory().'/release.json';

        if (! is_file($releaseFile)) {
            return null;
        }

        $release = json_decode((string) file_get_contents($releaseFile), true);
        $version = is_array($release) ? ($release['version'] ?? null) : null;

        return is_string($version) && preg_match(self::VERSION_PATTERN, $version) === 1 ? $version : null;
    }

    /**
     * @return array{tag: string, name: string, notes: string, published_at: string|null, html_url: string|null, package: array{url: string, size: int}|null, checksum_url: string|null}|null
     */
    public function latestRelease(bool $fresh = false): ?array
    {
        return $this->releases->latestRelease($fresh);
    }

    public function isNewerThanCurrent(string $version): bool
    {
        $currentVersion = $this->currentVersion();

        return $currentVersion === null
            || version_compare(ltrim($version, 'vV'), ltrim($currentVersion, 'vV'), '>');
    }

    /**
     * Why updates cannot run on this installation, or null when they can.
     */
    public function unavailabilityReason(): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return 'افزونه ZipArchive روی PHP سرور فعال نیست.';
        }

        $domainRoot = $this->resolveDomainRoot();

        if ($domainRoot === null) {
            return 'این نصب ساختار core/public_html ندارد. برای محیط توسعه از git استفاده کنید یا UPDATER_DOMAIN_ROOT را تنظیم کنید.';
        }

        if (is_dir($this->coreDirectory().'/.git')) {
            return 'این نسخه یک مخزن git است و نباید با بروزرسان داخلی جایگزین شود.';
        }

        foreach ([$this->coreDirectory(), $this->publicDirectory()] as $directory) {
            if (! is_dir($directory) || ! is_writable($directory)) {
                return 'پوشه‌های core و public_html باید وجود داشته باشند و قابل نوشتن باشند.';
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function state(): ?array
    {
        if (! is_file($this->statePath())) {
            return null;
        }

        $state = json_decode((string) file_get_contents($this->statePath()), true);

        return is_array($state) ? $state : null;
    }

    /**
     * @return list<array{name: string, version: string, created_at: string, size: int}>
     */
    public function backups(): array
    {
        $backups = [];

        foreach (glob($this->backupsDirectory().'/*.zip') ?: [] as $path) {
            if (preg_match(self::BACKUP_NAME_PATTERN, basename($path), $matches) !== 1) {
                continue;
            }

            $backups[] = [
                'name' => basename($path),
                'version' => $matches[2],
                'created_at' => $matches[1],
                'size' => (int) filesize($path),
            ];
        }

        usort($backups, fn (array $first, array $second): int => strcmp($second['name'], $first['name']));

        return $backups;
    }

    // ---------------------------------------------------------------------
    // Step 1: prepare
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function prepareLatestRelease(?int $userId): array
    {
        return $this->locked(function () use ($userId): array {
            $this->assertUpdatable();

            $release = $this->releases->latestRelease(fresh: true);

            if ($release === null) {
                throw new UpdateFailed('هیچ نسخه منتشرشده‌ای در GitHub پیدا نشد.');
            }

            if (preg_match(self::VERSION_PATTERN, $release['tag']) !== 1) {
                throw new UpdateFailed("برچسب نسخه «{$release['tag']}» معتبر نیست. از قالبی مانند v1.2.3 استفاده کنید.");
            }

            if (! $this->isNewerThanCurrent($release['tag'])) {
                throw new UpdateFailed('نسخه نصب‌شده از آخرین نسخه منتشرشده جدیدتر یا با آن برابر است.');
            }

            if ($release['package'] === null || $release['checksum_url'] === null) {
                throw new UpdateFailed('فایل‌های بسته این نسخه هنوز در GitHub آماده نشده‌اند. چند دقیقه بعد دوباره بررسی کنید.');
            }

            $maxPackageBytes = (int) config('updater.max_package_bytes');

            if ($release['package']['size'] > $maxPackageBytes) {
                throw new UpdateFailed('حجم بسته بروزرسانی بیش از حد مجاز است.');
            }

            $this->resetWorkArea();
            $this->assertFreeDiskSpace($release['package']['size']);

            $packagePath = $this->workDirectory().'/package.zip';
            $expectedHash = $this->releases->downloadChecksum($release['checksum_url']);
            $this->releases->downloadPackage($release['package']['url'], $packagePath, $maxPackageBytes);

            if (! hash_equals($expectedHash, (string) hash_file('sha256', $packagePath))) {
                $this->resetWorkArea();

                throw new UpdateFailed('checksum بسته دانلودشده مطابقت ندارد. بسته نصب نشد.');
            }

            $this->stagePackage($packagePath);

            $stagedVersion = $this->versionInDirectory($this->stagingDirectory());

            if ($stagedVersion !== $release['tag']) {
                $this->resetWorkArea();

                throw new UpdateFailed('نسخه داخل بسته با برچسب Release مطابقت ندارد.');
            }

            return $this->writePreparedState('release', $release['tag'], $expectedHash, $userId);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareRollback(string $backupName, ?int $userId): array
    {
        return $this->locked(function () use ($backupName, $userId): array {
            $this->assertUpdatable();

            if (preg_match(self::BACKUP_NAME_PATTERN, $backupName, $matches) !== 1 || ! is_file($this->backupsDirectory().'/'.$backupName)) {
                throw new UpdateFailed('فایل پشتیبان انتخاب‌شده پیدا نشد.');
            }

            $backupPath = $this->backupsDirectory().'/'.$backupName;

            $this->resetWorkArea();
            $this->assertFreeDiskSpace((int) filesize($backupPath));
            $this->stagePackage($backupPath);

            $version = $this->versionInDirectory($this->stagingDirectory()) ?? $matches[2];

            return $this->writePreparedState('rollback', $version, (string) hash_file('sha256', $backupPath), $userId);
        });
    }

    public function discardPrepared(): void
    {
        $this->locked(function (): void {
            if (($this->state()['stage'] ?? null) === self::STAGE_INSTALLED) {
                throw new UpdateFailed('فایل‌های جدید نصب شده‌اند؛ مرحله نهایی را اجرا کنید یا به نسخه قبل برگردید.');
            }

            $this->resetWorkArea();
        });
    }

    // ---------------------------------------------------------------------
    // Step 2: install
    // ---------------------------------------------------------------------

    /**
     * Put the site in maintenance mode and replace the managed files.
     *
     * On failure the files from the backup taken in step 1 are restored and
     * the site is brought back up.
     */
    public function install(): void
    {
        $this->locked(function (): void {
            $state = $this->state();

            if (($state['stage'] ?? null) !== self::STAGE_PREPARED || ! is_dir($this->stagingDirectory())) {
                throw new UpdateFailed('ابتدا مرحله آماده‌سازی بروزرسانی را اجرا کنید.');
            }

            $this->assertUpdatable();

            $package = $this->package();
            $stagedFiles = $package->managedFilesIn($this->stagingDirectory());
            $previousFiles = $this->manifestFiles();

            $this->enableMaintenanceMode((string) $state['bypass_secret']);

            try {
                $this->copyFiles($this->stagingDirectory(), $stagedFiles);
                $this->removeStaleFiles($previousFiles, $stagedFiles);
                $this->writeManifest($stagedFiles, (string) $state['package_sha256'], (string) $state['source']);
                $this->clearRuntimeCaches();
            } catch (Throwable $exception) {
                report($exception);
                $this->restoreBackup((string) $state['backup'], $stagedFiles);
                Artisan::call('up');

                throw new UpdateFailed('جایگزینی فایل‌ها ناموفق بود؛ فایل‌های نسخه قبلی بازگردانده شد. جزئیات در لاگ ثبت شد.');
            }

            $this->writeState([...$state, 'stage' => self::STAGE_INSTALLED, 'installed_at' => now()->toIso8601String()]);
        });
    }

    // ---------------------------------------------------------------------
    // Step 3: finalize (runs in a new request, on the new code)
    // ---------------------------------------------------------------------

    /**
     * @return array{version: string, migration_output: string}
     */
    public function finalize(): array
    {
        return $this->locked(function (): array {
            $state = $this->state();

            if (($state['stage'] ?? null) !== self::STAGE_INSTALLED) {
                throw new UpdateFailed('بروزرسانی نصب‌شده‌ای برای نهایی‌سازی وجود ندارد.');
            }

            $migrationExitCode = Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
            $migrationOutput = trim(Artisan::output());

            if ($migrationExitCode !== 0) {
                Log::error('Updater migration failed.', ['output' => $migrationOutput]);

                throw new UpdateFailed('اجرای Migration ناموفق بود. سایت در حالت Maintenance باقی ماند؛ دوباره تلاش کنید یا به نسخه قبل برگردید.');
            }

            $this->optimizer->optimize();

            Artisan::call('up');

            $this->recordActivity($state);
            $this->resetWorkArea();
            $this->pruneBackups();
            Cache::forget(GitHubReleaseClient::CACHE_KEY);

            Log::notice('Application updated.', [
                'source' => $state['source'] ?? null,
                'from' => $state['from_version'] ?? null,
                'to' => $state['version'] ?? null,
            ]);

            return [
                'version' => (string) ($state['version'] ?? ''),
                'migration_output' => Str::limit($migrationOutput, 4000),
            ];
        });
    }

    // ---------------------------------------------------------------------
    // Paths
    // ---------------------------------------------------------------------

    public function domainRoot(): string
    {
        return $this->resolveDomainRoot() ?? throw new UpdateFailed('مسیر ریشه دامنه برای بروزرسانی مشخص نیست.');
    }

    public function coreDirectory(): string
    {
        $domainRoot = $this->resolveDomainRoot();

        return $domainRoot === null ? base_path() : $domainRoot.'/'.config('updater.core_directory', 'core');
    }

    public function publicDirectory(): string
    {
        return $this->domainRoot().'/'.config('updater.public_directory', 'public_html');
    }

    public function workDirectory(): string
    {
        return storage_path('app/private/updates');
    }

    private function stagingDirectory(): string
    {
        return $this->workDirectory().'/staging';
    }

    private function backupsDirectory(): string
    {
        return $this->workDirectory().'/backups';
    }

    private function statePath(): string
    {
        return $this->workDirectory().'/state.json';
    }

    private function resolveDomainRoot(): ?string
    {
        $configuredRoot = config('updater.domain_root');

        if (is_string($configuredRoot) && $configuredRoot !== '') {
            return rtrim($configuredRoot, '/');
        }

        return basename(base_path()) === config('updater.core_directory', 'core')
            ? dirname(base_path())
            : null;
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    private function locked(Closure $callback): mixed
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        try {
            return Cache::lock(self::LOCK_KEY, 1800)->block(1, $callback);
        } catch (LockTimeoutException) {
            throw new UpdateFailed('یک عملیات بروزرسانی دیگر در حال اجراست.');
        }
    }

    private function assertUpdatable(): void
    {
        $reason = $this->unavailabilityReason();

        if ($reason !== null) {
            throw new UpdateFailed($reason);
        }
    }

    private function assertFreeDiskSpace(int $packageBytes): void
    {
        File::ensureDirectoryExists($this->workDirectory(), 0700);

        $freeBytes = @disk_free_space($this->workDirectory());
        $requiredBytes = ($packageBytes * 4) + (100 * 1024 * 1024);

        if ($freeBytes !== false && $freeBytes < $requiredBytes) {
            throw new UpdateFailed('فضای خالی دیسک برای بروزرسانی کافی نیست.');
        }
    }

    private function package(): ReleasePackage
    {
        return new ReleasePackage(
            (string) config('updater.core_directory', 'core'),
            (string) config('updater.public_directory', 'public_html'),
        );
    }

    private function stagePackage(string $zipPath): void
    {
        $this->package()->extract(
            $zipPath,
            $this->stagingDirectory(),
            (int) config('updater.max_files'),
            (int) config('updater.max_uncompressed_bytes'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function writePreparedState(string $source, string $version, string $packageHash, ?int $userId): array
    {
        $currentVersion = $this->currentVersion();
        $backupName = now()->format('Ymd-His').'__'.$this->safeVersionLabel($currentVersion).'.zip';
        $currentFiles = $this->manifestFiles() ?? $this->package()->managedFilesIn($this->domainRoot());

        $this->package()->create($this->domainRoot(), $currentFiles, $this->backupsDirectory().'/'.$backupName);

        $state = [
            'stage' => self::STAGE_PREPARED,
            'source' => $source,
            'version' => $version,
            'from_version' => $currentVersion,
            'package_sha256' => $packageHash,
            'backup' => $backupName,
            'bypass_secret' => Str::random(40),
            'user_id' => $userId,
            'prepared_at' => now()->toIso8601String(),
        ];

        $this->writeState($state);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function writeState(array $state): void
    {
        File::ensureDirectoryExists($this->workDirectory(), 0700);

        if (file_put_contents($this->statePath(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            throw new UpdateFailed('ذخیره وضعیت بروزرسانی ناموفق بود.');
        }
    }

    private function resetWorkArea(): void
    {
        File::deleteDirectory($this->stagingDirectory());
        File::deleteDirectory($this->workDirectory().'/restore');
        File::delete([$this->workDirectory().'/package.zip', $this->statePath()]);
    }

    private function versionInDirectory(string $directory): ?string
    {
        $releaseFile = $directory.'/'.config('updater.core_directory', 'core').'/release.json';
        $release = is_file($releaseFile) ? json_decode((string) file_get_contents($releaseFile), true) : null;
        $version = is_array($release) ? ($release['version'] ?? null) : null;

        return is_string($version) && preg_match(self::VERSION_PATTERN, $version) === 1 ? $version : null;
    }

    private function safeVersionLabel(?string $version): string
    {
        return $version === null ? 'unknown' : (string) preg_replace('/[^A-Za-z0-9._-]/', '-', $version);
    }

    /**
     * Files installed by the previous deployment (shared with the old GitHub deploy handler).
     *
     * @return list<string>|null
     */
    private function manifestFiles(): ?array
    {
        $manifestPath = $this->domainRoot().'/'.self::MANIFEST_FILE;

        if (! is_file($manifestPath)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest) || ! is_array($manifest['files'] ?? null)) {
            return null;
        }

        return array_values(array_filter($manifest['files'], 'is_string'));
    }

    /**
     * @param  list<string>  $files
     */
    private function writeManifest(array $files, string $packageHash, string $source): void
    {
        $manifest = [
            'version' => 5,
            'deployed_at' => gmdate('c'),
            'transport' => 'in-app-updater:'.$source,
            'package_sha256' => $packageHash,
            'files' => $files,
        ];

        $this->writeFileAtomically(
            $this->domainRoot().'/'.self::MANIFEST_FILE,
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @param  list<string>  $relativePaths
     */
    private function copyFiles(string $sourceRoot, array $relativePaths): void
    {
        $package = $this->package();

        foreach ($relativePaths as $relativePath) {
            $package->assertManagedPath($relativePath);

            $contents = file_get_contents($sourceRoot.'/'.$relativePath);

            if ($contents === false) {
                throw new UpdateFailed("خواندن فایل {$relativePath} ناموفق بود.");
            }

            $this->writeFileAtomically($this->domainRoot().'/'.$relativePath, $contents);
        }
    }

    /**
     * @param  list<string>|null  $previousFiles
     * @param  list<string>  $currentFiles
     */
    private function removeStaleFiles(?array $previousFiles, array $currentFiles): void
    {
        if ($previousFiles === null) {
            // Without a manifest we cannot know which files we own; never guess.
            return;
        }

        $package = $this->package();
        $currentFileMap = array_fill_keys($currentFiles, true);

        foreach ($previousFiles as $previousFile) {
            if (isset($currentFileMap[$previousFile])) {
                continue;
            }

            try {
                $package->assertManagedPath($package->normalizePath($previousFile));
            } catch (UpdateFailed) {
                continue;
            }

            $path = $this->domainRoot().'/'.$previousFile;

            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @param  list<string>  $filesOfFailedInstall
     */
    private function restoreBackup(string $backupName, array $filesOfFailedInstall): void
    {
        $restoreDirectory = $this->workDirectory().'/restore';

        File::deleteDirectory($restoreDirectory);

        $restoredFiles = $this->package()->extract(
            $this->backupsDirectory().'/'.$backupName,
            $restoreDirectory,
            (int) config('updater.max_files'),
            (int) config('updater.max_uncompressed_bytes'),
        );

        $this->copyFiles($restoreDirectory, $restoredFiles);
        $this->removeStaleFiles($filesOfFailedInstall, $restoredFiles);
        $this->writeManifest($restoredFiles, (string) hash_file('sha256', $this->backupsDirectory().'/'.$backupName), 'restore');
        $this->clearRuntimeCaches();

        File::deleteDirectory($restoreDirectory);
    }

    private function writeFileAtomically(string $destination, string $contents): void
    {
        File::ensureDirectoryExists(dirname($destination), 0755);

        $temporaryPath = $destination.'.upd-'.bin2hex(random_bytes(4));

        if (file_put_contents($temporaryPath, $contents) === false) {
            throw new UpdateFailed('نوشتن فایل‌های جدید ناموفق بود.');
        }

        @chmod($temporaryPath, 0644);

        if (! @rename($temporaryPath, $destination)) {
            @unlink($temporaryPath);

            throw new UpdateFailed('جایگزینی فایل‌های جدید ناموفق بود.');
        }
    }

    private function enableMaintenanceMode(string $bypassSecret): void
    {
        // A prerendered page is served by public_html/index.php without
        // loading Composer, so visitors never hit half-replaced vendor files.
        Artisan::call('down', [
            '--secret' => $bypassSecret,
            '--render' => 'errors::503',
            '--retry' => 60,
        ]);
    }

    private function clearRuntimeCaches(): void
    {
        $bootstrapCache = $this->coreDirectory().'/bootstrap/cache';

        File::delete([
            $bootstrapCache.'/config.php',
            $bootstrapCache.'/events.php',
            $bootstrapCache.'/compiled.php',
            ...(glob($bootstrapCache.'/routes-*.php') ?: []),
            ...(glob($this->coreDirectory().'/storage/framework/views/*.php') ?: []),
        ]);

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function pruneBackups(): void
    {
        $keep = max(1, (int) config('updater.keep_backups', 3));

        foreach (array_slice($this->backups(), $keep) as $backup) {
            File::delete($this->backupsDirectory().'/'.$backup['name']);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function recordActivity(array $state): void
    {
        $isRollback = ($state['source'] ?? null) === 'rollback';

        ActivityLog::record($isRollback ? 'system.rollback' : 'system.update', sprintf(
            '%s از نسخه %s به نسخه %s',
            $isRollback ? 'بازگردانی' : 'بروزرسانی',
            $state['from_version'] ?? 'نامشخص',
            $state['version'] ?? 'نامشخص',
        ));
    }
}
