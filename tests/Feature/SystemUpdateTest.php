<?php

namespace Tests\Feature;

use App\Livewire\System\Update as SystemUpdatePage;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ApplicationOptimizer;
use App\Services\Updates\ApplicationUpdater;
use App\Services\Updates\GitHubReleaseClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class SystemUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const ASSET_URL = 'https://api.github.com/repos/acme/app/releases/assets/1';

    private const CHECKSUM_URL = 'https://api.github.com/repos/acme/app/releases/assets/2';

    private string $domainRoot;

    private string $originalStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domainRoot = sys_get_temp_dir().'/rdsco-updater-'.bin2hex(random_bytes(6));
        $this->originalStoragePath = $this->app->storagePath();

        // Current installation: v1.0.0 with a file that the new release drops.
        $this->putFile('core/release.json', json_encode(['version' => 'v1.0.0']));
        $this->putFile('core/artisan', 'old artisan');
        $this->putFile('core/vendor/autoload.php', '<?php // old autoload');
        $this->putFile('core/bootstrap/app.php', '<?php // old bootstrap');
        $this->putFile('core/app/Removed.php', '<?php // removed in v2');
        $this->putFile('core/.env', 'APP_KEY=keep-me');
        $this->putFile('core/storage/app/private/customer.txt', 'keep me');
        $this->putFile('public_html/index.php', '<?php // old index');
        $this->putFile('public_html/storage/upload.jpg', 'keep me');
        $this->putFile('public_html/.well-known/acme', 'keep me');
        $this->putFile('.deploy-current-manifest.json', json_encode(['files' => [
            'core/release.json',
            'core/artisan',
            'core/vendor/autoload.php',
            'core/bootstrap/app.php',
            'core/app/Removed.php',
            'public_html/index.php',
        ]]));

        $this->app->useStoragePath($this->domainRoot.'/core/storage');
        File::ensureDirectoryExists($this->domainRoot.'/core/storage/framework/views');

        config([
            'updater.repository' => 'acme/app',
            'updater.domain_root' => $this->domainRoot,
            'updater.token' => null,
        ]);

        $this->mock(ApplicationOptimizer::class)->shouldReceive('optimize');
    }

    protected function tearDown(): void
    {
        File::delete($this->domainRoot.'/core/storage/framework/down');
        $this->app->useStoragePath($this->originalStoragePath);
        File::deleteDirectory($this->domainRoot);

        parent::tearDown();
    }

    public function test_an_administrator_can_install_the_latest_release_in_three_steps(): void
    {
        $admin = User::factory()->create();
        $package = $this->fakeRelease('v2.0.0');

        $this->actingAs($admin)
            ->postJson(route('system-update.prepare'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('prepared', app(ApplicationUpdater::class)->state()['stage']);
        $this->assertCount(1, app(ApplicationUpdater::class)->backups());
        $this->assertFileExists($this->domainRoot.'/core/app/Removed.php', 'Live files must not change during prepare.');

        $installResponse = $this->actingAs($admin)
            ->postJson(route('system-update.install'))
            ->assertOk()
            ->assertCookie('laravel_maintenance', null, false);

        $this->assertTrue($this->app->isDownForMaintenance());
        $this->assertStringEqualsFile($this->domainRoot.'/core/artisan', 'new artisan');
        $this->assertStringEqualsFile($this->domainRoot.'/public_html/index.php', '<?php // new index');
        $this->assertFileExists($this->domainRoot.'/core/app/Added.php');
        $this->assertFileDoesNotExist($this->domainRoot.'/core/app/Removed.php');
        $this->assertStringEqualsFile($this->domainRoot.'/core/.env', 'APP_KEY=keep-me');
        $this->assertStringEqualsFile($this->domainRoot.'/core/storage/app/private/customer.txt', 'keep me');
        $this->assertStringEqualsFile($this->domainRoot.'/public_html/storage/upload.jpg', 'keep me');
        $this->assertStringEqualsFile($this->domainRoot.'/public_html/.well-known/acme', 'keep me');

        $manifest = json_decode((string) file_get_contents($this->domainRoot.'/.deploy-current-manifest.json'), true);
        $this->assertSame(hash_file('sha256', $package), $manifest['package_sha256']);
        $this->assertContains('core/app/Added.php', $manifest['files']);
        $this->assertNotContains('core/app/Removed.php', $manifest['files']);

        // Without the bypass cookie the site is in maintenance mode...
        $this->actingAs($admin)->postJson(route('system-update.finalize'))->assertStatus(503);

        // ...but the browser that started the update can finish it.
        $this->actingAs($admin)
            ->withCredentials()
            ->withUnencryptedCookie('laravel_maintenance', $installResponse->getCookie('laravel_maintenance', false)->getValue())
            ->postJson(route('system-update.finalize'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertFalse($this->app->isDownForMaintenance());
        $this->assertSame('v2.0.0', app(ApplicationUpdater::class)->currentVersion());
        $this->assertNull(app(ApplicationUpdater::class)->state());
        $this->assertDirectoryDoesNotExist($this->domainRoot.'/core/storage/app/private/updates/staging');
        $this->assertDatabaseHas(ActivityLog::class, ['user_id' => $admin->getKey(), 'action' => 'system.update']);
    }

    public function test_an_administrator_can_roll_back_to_a_backup(): void
    {
        $admin = User::factory()->create();
        $this->fakeRelease('v2.0.0');
        $updater = app(ApplicationUpdater::class);

        $updater->prepareLatestRelease($admin->getKey());
        $updater->install();
        $updater->finalize();
        $this->assertSame('v2.0.0', $updater->currentVersion());

        $backup = collect($updater->backups())->firstWhere('version', 'v1.0.0');

        $this->actingAs($admin)
            ->postJson(route('system-update.rollback.prepare'), ['backup' => $backup['name']])
            ->assertOk();

        $updater->install();
        $updater->finalize();

        $this->assertSame('v1.0.0', $updater->currentVersion());
        $this->assertStringEqualsFile($this->domainRoot.'/core/artisan', 'old artisan');
        $this->assertFileExists($this->domainRoot.'/core/app/Removed.php');
        $this->assertFileDoesNotExist($this->domainRoot.'/core/app/Added.php');
        $this->assertStringEqualsFile($this->domainRoot.'/core/.env', 'APP_KEY=keep-me');
        $this->assertFalse($this->app->isDownForMaintenance());
    }

    public function test_a_package_with_a_wrong_checksum_is_rejected(): void
    {
        $admin = User::factory()->create();
        $this->fakeRelease('v2.0.0', checksum: str_repeat('a', 64));

        $this->actingAs($admin)
            ->postJson(route('system-update.prepare'))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertNull(app(ApplicationUpdater::class)->state());
        $this->assertStringEqualsFile($this->domainRoot.'/core/artisan', 'old artisan');
    }

    public function test_the_installed_version_is_not_reinstalled_or_downgraded(): void
    {
        $admin = User::factory()->create();
        $this->fakeRelease('v1.0.0');

        $this->actingAs($admin)
            ->postJson(route('system-update.prepare'))
            ->assertStatus(422);

        $this->assertNull(app(ApplicationUpdater::class)->state());
    }

    public function test_a_package_whose_version_does_not_match_the_tag_is_rejected(): void
    {
        $admin = User::factory()->create();
        $this->fakeRelease('v2.0.0', packagedVersion: 'v1.5.0');

        $this->actingAs($admin)
            ->postJson(route('system-update.prepare'))
            ->assertStatus(422);

        $this->assertNull(app(ApplicationUpdater::class)->state());
    }

    public function test_packages_with_protected_or_unsafe_paths_are_rejected(): void
    {
        $admin = User::factory()->create();

        foreach (['core/.env', 'core/storage/app/x.txt', 'public_html/.user.ini', 'other/file.php', '../escape.php'] as $unsafePath) {
            $this->fakeRelease('v2.0.0', extraFiles: [$unsafePath => 'bad']);

            $this->actingAs($admin)
                ->postJson(route('system-update.prepare'))
                ->assertStatus(422);

            $this->assertNull(app(ApplicationUpdater::class)->state(), $unsafePath);
            $this->assertStringEqualsFile($this->domainRoot.'/core/.env', 'APP_KEY=keep-me');
        }
    }

    public function test_guests_cannot_run_update_steps(): void
    {
        $this->postJson(route('system-update.prepare'))->assertUnauthorized();
        $this->postJson(route('system-update.install'))->assertUnauthorized();
    }

    public function test_updates_are_refused_inside_a_git_checkout(): void
    {
        File::ensureDirectoryExists($this->domainRoot.'/core/.git');

        $this->assertNotNull(app(ApplicationUpdater::class)->unavailabilityReason());

        $this->actingAs(User::factory()->create())
            ->postJson(route('system-update.prepare'))
            ->assertStatus(422);
    }

    public function test_the_page_shows_the_available_release(): void
    {
        $this->fakeRelease('v2.0.0', notes: "## Changes\n\n<script>alert(1)</script> fixed");
        $this->actingAs(User::factory()->create());

        Livewire::test(SystemUpdatePage::class)
            ->assertSet('currentVersion', 'v1.0.0')
            ->assertSet('updateAvailable', true)
            ->assertSee('v2.0.0')
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->call('startUpdate')
            ->assertDispatched('system-update-start');
    }

    /**
     * Build a release package and fake the GitHub API around it.
     *
     * @param  array<string, string>  $extraFiles
     */
    private function fakeRelease(
        string $tag,
        ?string $checksum = null,
        ?string $packagedVersion = null,
        array $extraFiles = [],
        string $notes = 'Bug fixes',
    ): string {
        $packagePath = $this->domainRoot.'/package-'.bin2hex(random_bytes(4)).'.zip';
        $zip = new ZipArchive;
        $zip->open($packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $files = [
            'core/release.json' => json_encode(['version' => $packagedVersion ?? $tag]),
            'core/artisan' => 'new artisan',
            'core/vendor/autoload.php' => '<?php // new autoload',
            'core/bootstrap/app.php' => '<?php // new bootstrap',
            'core/app/Added.php' => '<?php // added in v2',
            'public_html/index.php' => '<?php // new index',
            ...$extraFiles,
        ];

        foreach ($files as $path => $contents) {
            $zip->addFromString($path, $contents);
        }

        $zip->close();

        $packageContents = (string) file_get_contents($packagePath);
        $checksum ??= hash('sha256', $packageContents);

        // Start from a clean fake so repeated calls in one test replace earlier stubs.
        Http::swap(new HttpFactory);
        Http::fake([
            'api.github.com/repos/acme/app/releases/latest' => Http::response([
                'tag_name' => $tag,
                'name' => $tag,
                'body' => $notes,
                'published_at' => '2026-10-12T10:00:00Z',
                'html_url' => "https://github.com/acme/app/releases/tag/{$tag}",
                'assets' => [
                    ['name' => 'rdsco-release.zip', 'url' => self::ASSET_URL, 'size' => strlen($packageContents)],
                    ['name' => 'rdsco-release.zip.sha256', 'url' => self::CHECKSUM_URL, 'size' => 90],
                ],
            ]),
            self::ASSET_URL => Http::response($packageContents),
            self::CHECKSUM_URL => Http::response("{$checksum}  rdsco-release.zip\n"),
        ]);

        cache()->forget(GitHubReleaseClient::CACHE_KEY);

        return $packagePath;
    }

    private function putFile(string $relativePath, string $contents): void
    {
        File::ensureDirectoryExists(dirname($this->domainRoot.'/'.$relativePath));
        file_put_contents($this->domainRoot.'/'.$relativePath, $contents);
    }
}
