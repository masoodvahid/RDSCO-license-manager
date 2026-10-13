<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Rebuilds Laravel's caches (config, routes, events, views, icons) after an
 * update. Safe to run inside a web request on shared hosting.
 */
class ApplicationOptimizer
{
    public function optimize(): void
    {
        if (Artisan::call('optimize:clear', ['--no-interaction' => true]) !== 0) {
            throw new RuntimeException('Clearing the application cache failed.');
        }

        if (app()->environment('local', 'testing')) {
            return;
        }

        if (Artisan::call('optimize', ['--no-interaction' => true]) !== 0) {
            throw new RuntimeException('Optimizing the application failed.');
        }
    }
}
