<?php

namespace Rdsco\LicenseClient;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Rdsco\LicenseClient\Console\LicenseStatusCommand;
use Rdsco\LicenseClient\Http\ApiClient;
use Rdsco\LicenseClient\Middleware\EnsureLicensed;
use Throwable;

class LicenseClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rdsco-license.php', 'rdsco-license');

        $this->app->singleton(ApiClient::class, fn ($app) => new ApiClient($app->make(Factory::class), $app['config']['rdsco-license']));
        $this->app->singleton(LicenseManager::class, fn ($app) => new LicenseManager(
            $app->make(ApiClient::class),
            $app['cache']->store($app['config']['rdsco-license.cache_store']),
            $app['config']['rdsco-license'],
        ));
        $this->app->singleton(ShahkarClient::class, fn ($app) => new ShahkarClient($app->make(ApiClient::class)));
        $this->app->singleton(ErrorReporter::class, fn ($app) => new ErrorReporter(
            $app->make(ApiClient::class),
            $app['cache']->store($app['config']['rdsco-license.cache_store']),
            $app['config']['rdsco-license'],
        ));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/rdsco-license.php' => config_path('rdsco-license.php')], 'rdsco-license-config');

        $this->app->make(Router::class)->aliasMiddleware('rdsco.licensed', EnsureLicensed::class);

        if ($this->app->runningInConsole()) {
            $this->commands([LicenseStatusCommand::class]);
        }

        if (config('rdsco-license.report_errors') && filled(config('rdsco-license.key_id'))) {
            $handler = $this->app->make(ExceptionHandler::class);

            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (Throwable $exception): void {
                    $this->app->make(ErrorReporter::class)->report($exception);
                });
            }
        }
    }
}
