<?php

use App\Http\Controllers\Api\V1\AppDirectoryController;
use App\Http\Controllers\Api\V1\ErrorReportController;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\ShahkarController;
use App\Http\Middleware\AuthenticateLicense;
use Illuminate\Support\Facades\Route;

/*
| The "api" group is stateless: no session, cookies or CSRF. Order matters:
| the per-IP limit runs before authentication, the per-license limits after.
*/

Route::prefix('v1')->middleware('throttle:api-ip')->group(function (): void {
    Route::get('/ping', [MetaController::class, 'ping']);
    Route::get('/public-key', [MetaController::class, 'publicKey']);

    // Public: mobile apps resolve a 6-digit employer code to its API address.
    // Wrong guesses are throttled inside the controller.
    Route::post('/app/resolve', [AppDirectoryController::class, 'resolve']);

    Route::post('/license/verify', [LicenseController::class, 'verify'])
        ->middleware([AuthenticateLicense::class.':signature', 'throttle:license-verify']);

    Route::post('/errors', [ErrorReportController::class, 'store'])
        ->middleware([AuthenticateLicense::class.':strict', 'throttle:license-errors']);

    Route::post('/shahkar/verify', [ShahkarController::class, 'verify'])
        ->middleware([AuthenticateLicense::class.':strict', 'throttle:license-shahkar']);
});
