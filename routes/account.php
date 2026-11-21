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
use Sunrice\Account\Http\Controllers\ProfileController;
use Sunrice\Account\Http\Middleware\Authenticate;
use Sunrice\Account\Http\Middleware\RedirectIfAuthenticated;
use Sunrice\Account\Routing\AccountRoutes;

/** @var string $suffix */
Route::middleware(Authenticate::class)->group(function () use ($suffix): void {
    Route::get('/', [DashboardController::class, 'landing'])->name('sunrice.account.dashboard'.$suffix);
    Route::get('overview', [DashboardController::class, 'overview'])->name('sunrice.account.overview'.$suffix);
    Route::get('announcement', [DashboardController::class, 'announcement'])->name('sunrice.account.announcement'.$suffix);
    Route::get('profile', [ProfileController::class, 'show'])->name('sunrice.account.profile'.$suffix);
    Route::put('profile', [ProfileController::class, 'update'])->name('sunrice.account.profile.update'.$suffix);
    Route::put('profile/password', [ProfileController::class, 'password'])->name('sunrice.account.profile.password'.$suffix);
    Route::post('profile/delete', [ProfileController::class, 'requestDeletion'])->name('sunrice.account.profile.delete'.$suffix);
    Route::post('logout', [Auth\LoginController::class, 'destroy'])->name('sunrice.account.logout'.$suffix);
});

Route::middleware(RedirectIfAuthenticated::class)->group(function () use ($suffix): void {
    $login = AccountRoutes::path('login');
    $register = AccountRoutes::path('register');
    $forgot = AccountRoutes::path('forgot_password');
    $reset = AccountRoutes::path('reset_password');
    Route::get($login, [Auth\LoginController::class, 'show'])->name('sunrice.account.login'.$suffix);
    Route::post($login, [Auth\LoginController::class, 'store'])->name('sunrice.account.login.attempt'.$suffix);
    Route::get($register, [Auth\RegisterController::class, 'show'])->name('sunrice.account.register'.$suffix);
    Route::post($register, [Auth\RegisterController::class, 'store'])->middleware(ProtectAgainstSpam::class)->name('sunrice.account.register.store'.$suffix);
    Route::get($forgot, [Auth\ForgotPasswordController::class, 'show'])->name('sunrice.account.password.request'.$suffix);
    Route::post($forgot, [Auth\ForgotPasswordController::class, 'store'])->middleware(ProtectAgainstSpam::class)->name('sunrice.account.password.email'.$suffix);
    Route::get($reset.'/{token}', [Auth\ResetPasswordController::class, 'show'])->name('sunrice.account.password.reset'.$suffix);
    Route::post($reset, [Auth\ResetPasswordController::class, 'store'])->name('sunrice.account.password.update'.$suffix);
    // No honeypot: it's sent from the sign-in form (which a password manager may fill and send at once); throttled and behind the captcha.
    Route::post($login.'/code', [Auth\LoginCodeController::class, 'send'])->name('sunrice.account.code.send'.$suffix);
    Route::get($login.'/code', [Auth\LoginCodeController::class, 'show'])->name('sunrice.account.code'.$suffix);
    Route::post($login.'/code/check', [Auth\LoginCodeController::class, 'consume'])->name('sunrice.account.code.consume'.$suffix);
    Route::get($login.'/google', [Auth\GoogleController::class, 'redirect'])->name('sunrice.account.google'.$suffix);
});

// For guests (enter the email) and for a signed-in account still to verify its own (the link goes to it).
$verify = AccountRoutes::path('verify');
Route::get($verify, [Auth\VerificationController::class, 'show'])->name('sunrice.account.verification.notice'.$suffix);
Route::post($verify, [Auth\VerificationController::class, 'send'])->middleware(ProtectAgainstSpam::class)->name('sunrice.account.verification.send'.$suffix);
