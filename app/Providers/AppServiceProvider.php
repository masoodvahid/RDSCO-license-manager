<?php

namespace App\Providers;

use App\Http\Middleware\AuthenticateLicense;
use App\Services\Licensing\LicenseSnapshot;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Paginator::defaultView('pagination.default');

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api-ip', fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('license.rate_limits.per_ip', 600)),
        )->by('ip:'.$request->ip()));

        foreach (['verify', 'shahkar', 'errors'] as $endpoint) {
            RateLimiter::for("license-{$endpoint}", function (Request $request) use ($endpoint): Limit {
                $license = $request->attributes->get(AuthenticateLicense::ATTRIBUTE);
                $identity = $license instanceof LicenseSnapshot ? 'license:'.$license->id : 'ip:'.$request->ip();

                return Limit::perMinute(max(1, (int) config("license.rate_limits.{$endpoint}", 60)))
                    ->by("{$endpoint}:{$identity}");
            });
        }
    }
}
