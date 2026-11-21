<?php

declare(strict_types=1);

/*
 * The account pages, under the account prefix in every language
 * (AccountRoutes::register()). $suffix is '' or '.locale'.
 */

use Illuminate\Support\Facades\Route;
use Sunrice\Account\Http\Controllers\DashboardController;
use Sunrice\Account\Http\Middleware\Authenticate;

/** @var string $suffix */
Route::middleware(Authenticate::class)->group(function () use ($suffix): void {
    Route::get('/', DashboardController::class)->name('account.dashboard'.$suffix);
});
