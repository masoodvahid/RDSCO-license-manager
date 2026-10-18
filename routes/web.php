<?php

use App\Http\Controllers\Admin\InvoicePrintController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SystemUpdateController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

Route::get('/setup', [SetupController::class, 'show'])->name('setup');
Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:10,1')->name('setup.store');

Route::middleware('guest')->group(function (): void {
    Route::livewire('/login', Livewire\Auth\Login::class)->name('login');
    Route::livewire('/login/two-factor', Livewire\Auth\TwoFactorChallenge::class)->name('two-factor.challenge');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::livewire('/', Livewire\Dashboard::class)->name('dashboard');

    Route::livewire('/customers', Livewire\Customers\Index::class)->name('customers.index');
    Route::livewire('/customers/create', Livewire\Customers\Form::class)->name('customers.create');
    Route::livewire('/customers/{customer}', Livewire\Customers\Show::class)->name('customers.show');
    Route::livewire('/customers/{customer}/edit', Livewire\Customers\Form::class)->name('customers.edit');

    Route::livewire('/products', Livewire\Products\Index::class)->name('products.index');

    Route::livewire('/licenses', Livewire\Licenses\Index::class)->name('licenses.index');
    Route::livewire('/licenses/create', Livewire\Licenses\Form::class)->name('licenses.create');
    Route::livewire('/licenses/{license}', Livewire\Licenses\Show::class)->name('licenses.show');
    Route::livewire('/licenses/{license}/edit', Livewire\Licenses\Form::class)->name('licenses.edit');

    Route::livewire('/services', Livewire\Services\Index::class)->name('services.index');
    Route::livewire('/usage', Livewire\Usage\Index::class)->name('usage.index');

    Route::livewire('/invoices', Livewire\Invoices\Index::class)->name('invoices.index');
    Route::livewire('/invoices/create', Livewire\Invoices\Create::class)->name('invoices.create');
    Route::livewire('/invoices/{invoice}', Livewire\Invoices\Show::class)->name('invoices.show');
    Route::get('/invoices/{invoice}/print', InvoicePrintController::class)->name('invoices.print');

    Route::livewire('/payments', Livewire\Payments\Index::class)->name('payments.index');

    Route::livewire('/errors', Livewire\Errors\Index::class)->name('errors.index');
    Route::livewire('/errors/{error}', Livewire\Errors\Show::class)->name('errors.show');

    Route::livewire('/activity', Livewire\Activity\Index::class)->name('activity.index');

    Route::livewire('/settings', Livewire\Settings\General::class)->name('settings.general');
    Route::livewire('/settings/shahkar', Livewire\Settings\Shahkar::class)->name('settings.shahkar');
    Route::livewire('/profile', Livewire\Account\Profile::class)->name('profile');
    Route::livewire('/docs', Livewire\Docs::class)->name('docs');

    Route::livewire('/system/update', Livewire\System\Update::class)->name('system.update');
    Route::livewire('/system/health', Livewire\System\Health::class)->name('system.health');

    // In-app updater steps (JSON), driven by the System update page.
    Route::prefix('system-update')
        ->name('system-update.')
        ->middleware('throttle:30,1')
        ->controller(SystemUpdateController::class)
        ->group(function (): void {
            Route::post('/prepare', 'prepare')->name('prepare');
            Route::post('/rollback/prepare', 'prepareRollback')->name('rollback.prepare');
            Route::post('/install', 'install')->name('install');
            Route::post('/finalize', 'finalize')->name('finalize');
            Route::post('/discard', 'discard')->name('discard');
        });
});
