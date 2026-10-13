<?php

namespace App\Services\Updates;

use App\Exceptions\UpdateFailed;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Reads, validates and writes release packages.
 *
 * A package is a ZIP with two top-level directories, "core/" (the Laravel
 * application) and "public_html/" (the document root). The path rules are the
 * same ones the previous GitHub deploy handler enforced, so packages built by
 * either pipeline are interchangeable and the existing deployment manifest
 * stays valid.
 */
class ReleasePackage
{
    public function __construct(
        private readonly string $coreDirectory = 'core',
        private readonly string $publicDirectory = 'public_html',
    ) {}

    /**
     * Validate every entry of the package and extract it into $destination.
     *
     * @return list<string> Relative paths of all extracted files.
     */
    public function extract(
        string $zipPath,
        string $destination,
        int $maxFiles,
        int $maxUncompressedBytes,
    ): array {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new UpdateFailed('فایل بسته بروزرسانی قابل باز شدن نیست.');
        }

        try {
            if ($zip->numFiles < 1 || $zip->numFiles > $maxFiles) {
                throw new UpdateFailed('تعداد فایل‌های بسته بروزرسانی معتبر نیست.');
            }

            $entries = [];
            $totalBytes = 0;

            // Validate the whole archive before writing anything to disk.
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);

                if ($stat === false) {
                    throw new UpdateFailed('ساختار فایل بسته بروزرسانی معتبر نیست.');
                }

                $path = $this->normalizePath((string) $stat['name']);
                $isDirectory = str_ends_with($path, '/');
                $this->assertManagedPath($path, $isDirectory);

                if ($isDirectory) {
                    continue;
                }

                $totalBytes += (int) $stat['size'];

                if ($totalBytes > $maxUncompressedBytes) {
                    throw new UpdateFailed('حجم استخراج‌شده بسته بروزرسانی بیش از حد مجاز است.');
                }

                $entries[$index] = $path;
            }

            foreach (["{$this->coreDirectory}/artisan", "{$this->coreDirectory}/vendor/autoload.php", "{$this->coreDirectory}/bootstrap/app.php", "{$this->publicDirectory}/index.php"] as $requiredFile) {
                if (! in_array($requiredFile, $entries, true)) {
                    throw new UpdateFailed("بسته بروزرسانی ناقص است ({$requiredFile} وجود ندارد).");
                }
            }

            $this->ensureDirectory($destination);

            foreach ($entries as $index => $path) {
                $target = $destination.'/'.$path;
                $this->ensureDirectory(dirname($target));

                $source = $zip->getStream((string) $zip->getNameIndex($index));
                $output = $source === false ? false : fopen($target, 'wb');

                if ($source === false || $output === false) {
                    throw new UpdateFailed('استخراج فایل‌های بسته بروزرسانی ناموفق بود.');
                }

                $copied = stream_copy_to_stream($source, $output);
                fclose($source);
                fclose($output);

                if ($copied === false) {
                    throw new UpdateFailed('استخراج فایل‌های بسته بروزرسانی ناموفق بود.');
                }
            }

            return array_values($entries);
        } finally {
            $zip->close();
        }
    }

    /**
     * Zip the given files (relative to $root) into a package with the same layout.
     *
     * @param  list<string>  $relativePaths
     */
    public function create(string $root, array $relativePaths, string $zipPath): void
    {
        $this->ensureDirectory(dirname($zipPath));

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new UpdateFailed('ایجاد فایل پشتیبان ناموفق بود.');
        }

        foreach ($relativePaths as $relativePath) {
            $absolutePath = $root.'/'.$relativePath;

            if (is_file($absolutePath) && ! is_link($absolutePath)) {
                $zip->addFile($absolutePath, $relativePath);
            }
        }

        if (! $zip->close()) {
            throw new UpdateFailed('ذخیره فایل پشتیبان ناموفق بود.');
        }
    }

    /**
     * List the managed (non-protected) files currently installed under $root.
     *
     * @return list<string>
     */
    public function managedFilesIn(string $root): array
    {
        $files = [];

        foreach ([$this->coreDirectory, $this->publicDirectory] as $topLevel) {
            $directory = $root.'/'.$topLevel;

            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }

                $relativePath = $topLevel.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));

                if (! $this->isProtectedPath($relativePath) && ! str_starts_with($relativePath, "{$this->coreDirectory}/.git/")) {
                    $files[] = $relativePath;
                }
            }
        }

        sort($files);

        return $files;
    }

    public function normalizePath(string $name): string
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/')) {
            throw new UpdateFailed('بسته بروزرسانی شامل مسیر نامعتبر است.');
        }

        $isDirectory = str_ends_with($name, '/');
        $segments = explode('/', trim($name, '/'));

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new UpdateFailed('بسته بروزرسانی شامل مسیر نامعتبر است.');
            }
        }

        return implode('/', $segments).($isDirectory ? '/' : '');
    }

    public function assertManagedPath(string $path, bool $isDirectory = false): void
    {
        $plainPath = rtrim($path, '/');

        if ($plainPath === $this->coreDirectory || $plainPath === $this->publicDirectory) {
            if (! $isDirectory) {
                throw new UpdateFailed('بسته بروزرسانی شامل مسیر نامعتبر است.');
            }

            return;
        }

        if (! str_starts_with($plainPath, "{$this->coreDirectory}/") && ! str_starts_with($plainPath, "{$this->publicDirectory}/")) {
            throw new UpdateFailed('بسته بروزرسانی شامل مسیری خارج از core و public_html است.');
        }

        if ($this->isProtectedPath($plainPath)) {
            throw new UpdateFailed("بسته بروزرسانی شامل مسیر محافظت‌شده است: {$plainPath}");
        }
    }

    /**
     * Paths that belong to the server, never to a release.
     */
    public function isProtectedPath(string $path): bool
    {
        $path = rtrim($path, '/');
        $core = preg_quote($this->coreDirectory, '#');

        if (preg_match("#^{$core}/\.env(?:\.|$)#", $path) === 1) {
            return true;
        }

        foreach ([
            "{$this->coreDirectory}/storage",
            "{$this->publicDirectory}/storage",
            "{$this->publicDirectory}/.well-known",
        ] as $protectedDirectory) {
            if ($path === $protectedDirectory || str_starts_with($path, $protectedDirectory.'/')) {
                return true;
            }
        }

        return in_array($path, [
            "{$this->publicDirectory}/github-deploy-handler.php",
            "{$this->publicDirectory}/.user.ini",
            "{$this->publicDirectory}/php.ini",
        ], true);
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new UpdateFailed('ایجاد پوشه موردنیاز بروزرسانی ناموفق بود.');
        }
    }
}
