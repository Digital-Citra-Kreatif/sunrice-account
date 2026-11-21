<?php

declare(strict_types=1);

/*
 * The account pages, under the account prefix in every language
 * (AccountRoutes::register()). $suffix is '' or '.locale'.
 */

use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;
use Sunrice\Account\Http\Controllers\Auth;
use Sunrice\Account\Http\Controllers\DashboardController;
use Sunrice\Account\Http\Middleware\Authenticate;
use Sunrice\Account\Http\Middleware\RedirectIfAuthenticated;
use Sunrice\Account\Routing\AccountRoutes;

/** @var string $suffix */
Route::middleware(Authenticate::class)->group(function () use ($suffix): void {
    Route::get('/', DashboardController::class)->name('account.dashboard'.$suffix);
    Route::post('logout', [Auth\LoginController::class, 'destroy'])->name('account.logout'.$suffix);
});

Route::middleware(RedirectIfAuthenticated::class)->group(function () use ($suffix): void {
    $login = AccountRoutes::path('login');
    $register = AccountRoutes::path('register');
    $forgot = AccountRoutes::path('forgot_password');
    $reset = AccountRoutes::path('reset_password');

    Route::get($login, [Auth\LoginController::class, 'show'])->name('account.login'.$suffix);
    Route::post($login, [Auth\LoginController::class, 'store'])->middleware(ProtectAgainstSpam::class)->name('account.login.attempt'.$suffix);
    Route::get($register, [Auth\RegisterController::class, 'show'])->name('account.register'.$suffix);
    Route::post($register, [Auth\RegisterController::class, 'store'])->middleware(ProtectAgainstSpam::class)->name('account.register.store'.$suffix);
    Route::get($forgot, [Auth\ForgotPasswordController::class, 'show'])->name('account.password.request'.$suffix);
    Route::post($forgot, [Auth\ForgotPasswordController::class, 'store'])->middleware(ProtectAgainstSpam::class)->name('account.password.email'.$suffix);
    Route::get($reset.'/{token}', [Auth\ResetPasswordController::class, 'show'])->name('account.password.reset'.$suffix);
    Route::post($reset, [Auth\ResetPasswordController::class, 'store'])->name('account.password.update'.$suffix);
});
