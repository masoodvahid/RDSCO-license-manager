<?php

namespace App\Services\Updates;

use App\Exceptions\UpdateFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

/**
 * Reads the latest published release of the configured repository.
 *
 * Assets are downloaded through the API asset endpoint (not the browser URL)
 * so the same code works for public and private repositories.
 */
class GitHubReleaseClient
{
    public const CACHE_KEY = 'updater:latest-release';

    /**
     * @return array{tag: string, name: string, notes: string, published_at: string|null, html_url: string|null, package: array{url: string, size: int}|null, checksum_url: string|null}|null
     */
    public function latestRelease(bool $fresh = false): ?array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(
            self::CACHE_KEY,
            max(30, (int) config('updater.release_cache_seconds', 600)),
            fn (): ?array => $this->fetchLatestRelease(),
        );
    }

    public function downloadPackage(string $assetUrl, string $destination, int $maxBytes): void
    {
        $abortWhenTooLarge = function (int $expectedBytes, int $downloadedBytes) use ($maxBytes): void {
            if ($expectedBytes > $maxBytes || $downloadedBytes > $maxBytes) {
                throw new UpdateFailed('حجم بسته بروزرسانی بیش از حد مجاز است.');
            }
        };

        try {
            $response = $this->request()
                ->accept('application/octet-stream')
                ->timeout(max(60, (int) config('updater.download_timeout_seconds', 600)))
                ->withOptions(['progress' => $abortWhenTooLarge])
                ->sink($destination)
                ->get($assetUrl);
        } catch (ConnectionException) {
            @unlink($destination);

            throw new UpdateFailed('دانلود بسته بروزرسانی از GitHub ناموفق بود. اتصال سرور به GitHub را بررسی کنید.');
        }

        if (! $response->successful() || ! is_file($destination)) {
            @unlink($destination);

            throw new UpdateFailed("دانلود بسته بروزرسانی ناموفق بود (HTTP {$response->status()}).");
        }

        if (filesize($destination) > $maxBytes) {
            @unlink($destination);

            throw new UpdateFailed('حجم بسته بروزرسانی بیش از حد مجاز است.');
        }
    }

    public function downloadChecksum(string $assetUrl): string
    {
        try {
            $response = $this->request()
                ->accept('application/octet-stream')
                ->withOptions(['on_headers' => function (ResponseInterface $response): void {
                    if ((int) $response->getHeaderLine('Content-Length') > 4096) {
                        throw new UpdateFailed('فایل checksum بروزرسانی معتبر نیست.');
                    }
                }])
                ->get($assetUrl);
        } catch (ConnectionException) {
            throw new UpdateFailed('دریافت فایل checksum از GitHub ناموفق بود.');
        }

        if (! $response->successful() || preg_match('/\b([a-f0-9]{64})\b/i', $response->body(), $matches) !== 1) {
            throw new UpdateFailed('فایل checksum بروزرسانی معتبر نیست.');
        }

        return strtolower($matches[1]);
    }

    /**
     * @return array{tag: string, name: string, notes: string, published_at: string|null, html_url: string|null, package: array{url: string, size: int}|null, checksum_url: string|null}|null
     */
    private function fetchLatestRelease(): ?array
    {
        $repository = (string) config('updater.repository');

        if (preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository) !== 1) {
            throw new UpdateFailed('مخزن GitHub بروزرسانی به‌درستی تنظیم نشده است (UPDATER_REPOSITORY).');
        }

        try {
            $response = $this->request()
                ->accept('application/vnd.github+json')
                ->timeout(15)
                ->get(rtrim((string) config('updater.api_url'), '/')."/repos/{$repository}/releases/latest");
        } catch (ConnectionException) {
            throw new UpdateFailed('ارتباط با GitHub برقرار نشد.');
        }

        if ($response->status() === 404) {
            // No published release yet, or a private repository without a valid token.
            return null;
        }

        $this->throwForFailedResponse($response);

        $release = $response->json();

        if (! is_array($release) || ! is_string($release['tag_name'] ?? null)) {
            throw new UpdateFailed('پاسخ GitHub برای آخرین نسخه معتبر نیست.');
        }

        $assetName = (string) config('updater.asset_name');
        $assets = collect(is_array($release['assets'] ?? null) ? $release['assets'] : []);
        $package = $assets->firstWhere('name', $assetName);
        $checksum = $assets->firstWhere('name', $assetName.'.sha256');

        return [
            'tag' => $release['tag_name'],
            'name' => is_string($release['name'] ?? null) && $release['name'] !== '' ? $release['name'] : $release['tag_name'],
            'notes' => is_string($release['body'] ?? null) ? $release['body'] : '',
            'published_at' => $release['published_at'] ?? null,
            'html_url' => $release['html_url'] ?? null,
            'package' => is_array($package) && is_string($package['url'] ?? null)
                ? ['url' => $package['url'], 'size' => (int) ($package['size'] ?? 0)]
                : null,
            'checksum_url' => is_array($checksum) && is_string($checksum['url'] ?? null) ? $checksum['url'] : null,
        ];
    }

    private function throwForFailedResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 401) {
            throw new UpdateFailed('توکن GitHub نامعتبر است (UPDATER_GITHUB_TOKEN).');
        }

        if ($response->status() === 403 || $response->status() === 429) {
            throw new UpdateFailed('GitHub درخواست را محدود کرد. چند دقیقه بعد دوباره تلاش کنید یا توکن GitHub تنظیم کنید.');
        }

        throw new UpdateFailed("GitHub پاسخ ناموفق داد (HTTP {$response->status()}).");
    }

    private function request(): PendingRequest
    {
        $request = Http::withUserAgent('rdsco-api-manager-updater')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->connectTimeout(15);

        $token = config('updater.token');

        return is_string($token) && $token !== '' ? $request->withToken($token) : $request;
    }
}
