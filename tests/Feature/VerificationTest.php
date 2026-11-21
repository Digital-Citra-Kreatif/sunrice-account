<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Sunrice\Account\Auth\Verification;
use Sunrice\Account\Events\Verified;
use Sunrice\Account\Facades\Account;
use Sunrice\Account\Notifications\VerifyEmailNotification;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\AccountGuard;

it('verifies the email from the link, signs in and opens My Account in the link\'s language', function () {
    Event::fake([Verified::class]);
    $user = siteUser(['email_verified_at' => null]);

    $this->get(Verification::url($user, 'en'))->assertRedirect('/en/account')->assertSessionHas('account.status');

    expect($user->fresh()->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user, 'account');
    Event::assertDispatched(Verified::class, fn (Verified $e) => $e->locale === 'en');
});

it('emails a link that works', function () {
    Notification::fake();
    $user = siteUser(['email_verified_at' => null]);

    $this->post('/account/verify', ['email' => 'ana@example.com'])->assertSessionHas('account.status');

    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $n) use ($user) {
        $url = (string) $n->toMail($user)->actionUrl;
        $this->get($url)->assertRedirect('/account');

        return str_starts_with($url, 'http://localhost/account/verify/'.$user->id.'/');
    });
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('refuses a link that was changed, expired or sent to another email', function () {
    $user = siteUser(['email_verified_at' => null]);
    $url = Verification::url($user, 'id');

    // Each goes to the page asking for a new link, with why.
    $this->get($url.'x')->assertRedirect('http://localhost/account/verify')->assertSessionHas('account.error');
    $this->get(URL::temporarySignedRoute('sunrice.account.verify', now()->subMinute(), ['id' => $user->id, 'hash' => Verification::hash($user), 'lang' => 'id']))->assertRedirect('http://localhost/account/verify');
    $user->forceFill(['email' => 'new@example.com'])->save();
    $this->get($url)->assertRedirect('http://localhost/account/verify');

    expect($user->fresh()->email_verified_at)->toBeNull();
    $this->assertGuest('account');
});

it('verifies staff but doesn\'t sign them in while the site shares the admin\'s guard', function () {
    config(['sunrice-account.auth.guard' => 'web']);
    app(SyncPermissions::class)->handle();
    $staff = siteUser(['email_verified_at' => null]);
    $staff->assignRole(Role::findOrCreate('staff', 'web')->givePermissionTo(Panel::ACCESS));

    $this->get(Verification::url($staff, 'id'))->assertRedirect('/account/login');

    expect($staff->fresh()->email_verified_at)->not->toBeNull();
    $this->assertGuest('web');
});

it('doesn\'t sign in a locked account', function () {
    $user = siteUser(['email_verified_at' => null]);
    app(AccountGuard::class)->lock($user, 'test');

    $this->get(Verification::url($user, 'id'))->assertRedirect('/account/login');
    $this->assertGuest('account');
});

it('answers the same whoever asks for a new link', function () {
    Notification::fake();
    siteUser(['email' => 'verified@example.com']);

    $this->get('/account/verify')->assertOk()->assertSee('Verifikasi email Anda');
    $unknown = $this->post('/account/verify', ['email' => 'who@example.com'])->assertSessionHas('account.status');
    $verified = $this->post('/account/verify', ['email' => 'verified@example.com'])->assertSessionHas('account.status');

    Notification::assertNothingSent();
});

it('slows down repeated requests for a link', function () {
    Notification::fake();
    foreach (range(1, 3) as $i) {
        $this->post('/account/verify', ['email' => 'ana@example.com']);
    }

    $this->post('/account/verify', ['email' => 'ana@example.com'])->assertSessionHasErrors('email');
});

it('keeps unverified accounts out of pages that need a verified email', function () {
    Account::routes(function (string $suffix): void {
        Route::get('checkout', fn () => 'checkout')->middleware('sunrice.account.verified')->name('test.checkout'.$suffix);
    });

    $this->actingAs(siteUser(['email_verified_at' => null]), 'account');
    $this->get('/account/checkout')->assertRedirect('/account/verify');

    accountSettings(['verifyEmail' => false]);
    $this->get('/account/checkout')->assertOk();

    $this->actingAs(siteUser(['email' => 'v@example.com']), 'account');
    accountSettings(['verifyEmail' => true]);
    $this->get('/account/checkout')->assertOk();
});
