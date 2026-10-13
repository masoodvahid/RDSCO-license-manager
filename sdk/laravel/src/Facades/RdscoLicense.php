<?php

namespace Rdsco\LicenseClient\Facades;

use Illuminate\Support\Facades\Facade;
use Rdsco\LicenseClient\LicenseManager;

/**
 * @method static \Rdsco\LicenseClient\LicenseStatus status(bool $forceRefresh = false)
 * @method static bool isValid()
 * @method static mixed feature(string $key, mixed $default = null)
 * @method static void forget()
 */
class RdscoLicense extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LicenseManager::class;
    }
}
