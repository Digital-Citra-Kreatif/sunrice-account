<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Sunrice\Account\Events\SignedIn;
use Sunrice\Account\Events\SignedOut;
use Sunrice\Account\Notifications\VerifyEmailNotification;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\AccountGuard;

it('shows the sign-in page in the language of the address', function () {
    $this->get('/account/login')->assertOk()->assertSee('Masuk')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/en/account/login')->assertOk()->assertSee('Sign in')->assertSee('Create one');
});

it('signs an account in on the site only, and goes to My Account', function () {
    Event::fake([SignedIn::class]);
    $user = siteUser();

    $this->post('/account/login', ['email' => 'ANA@example.com', 'password' => 'secret-pass'])->assertRedirect('/account');

    $this->assertAuthenticatedAs($user, 'account');
    $this->assertGuest('web');
    Event::assertDispatched(SignedIn::class, fn (SignedIn $e) => $e->user->getAuthIdentifier() === $user->id && $e->method === 'password');
});

it('answers the same for a wrong password and an unknown email', function () {
    siteUser();

    $wrong = $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');
    $unknown = $this->post('/account/login', ['email' => 'who@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toBe('Data ini tidak cocok dengan akun mana pun.');
    $this->assertGuest('account');
});

it('slows down repeated attempts', function () {
    siteUser();
    foreach (range(1, 5) as $i) {
        $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'nope']);
    }

    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Terlalu banyak percobaan');
    $this->assertGuest('account');
});

it('asks an unverified account to verify its email, sending a new link once a minute', function () {
    Notification::fake();
    $user = siteUser(['email_verified_at' => null]);

    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toBe('Verifikasi alamat email Anda dulu: kami sudah mengirimkan tautannya.');
    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertSessionHasErrors('email');

    $this->assertGuest('account');
    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);
});

it('signs in an unverified account while emails aren\'t verified', function () {
    siteUser(['email_verified_at' => null]);
    accountSettings(['verifyEmail' => false]);

    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertRedirect('/account');
});

it('refuses a locked account', function () {
    $user = siteUser();
    app(AccountGuard::class)->lock($user, 'test');

    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertSessionHasErrors('email');
    $this->assertGuest('account');
});

it('sends staff to the admin\'s sign-in while the site shares the admin\'s guard', function () {
    config(['sunrice-account.auth.guard' => 'web']);
    app(SyncPermissions::class)->handle();
    $staff = siteUser(['email' => 'staff@example.com']);
    $staff->assignRole(Role::findOrCreate('staff', 'web')->givePermissionTo(Panel::ACCESS));
    siteUser();

    $this->post('/account/login', ['email' => 'staff@example.com', 'password' => 'secret-pass'])->assertSessionHasErrors('email');
    $this->assertGuest('web');

    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertRedirect('/account');
    $this->assertAuthenticated('web');
});

it('checks the captcha when one is set up', function () {
    config(['sunrice-admin.captcha' => ['provider' => 'turnstile', 'site_key' => 'site', 'secret_key' => 'secret']]);
    Http::fake(['*' => Http::response(['success' => false])]);
    siteUser();

    $this->get('/account/login')->assertSee('data-sitekey="site"', false);
    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertSessionHasErrors('captcha');
    $this->assertGuest('account');
});

it('goes back to the page a guest was going to', function () {
    siteUser();
    $this->get('/account?tab=orders')->assertRedirect('/account/login');

    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertRedirect('http://localhost/account?tab=orders');
});

it('goes to the page set in the settings after signing in', function () {
    siteUser();
    accountSettings(['redirectAfterLogin' => 'welcome']);

    $this->post('/en/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertRedirect('http://localhost/en/welcome');
});

it('sends signed-in accounts from the sign-in page to My Account', function () {
    $this->actingAs(siteUser(), 'account');

    $this->get('/account/login')->assertRedirect('/account');
});

it('signs out of the site only, keeping the admin session', function () {
    Event::fake([SignedOut::class]);
    $user = siteUser();
    $this->actingAs($user, 'web')->actingAs($user, 'account');

    $this->post('/account/logout')->assertRedirect('http://localhost');

    $this->assertGuest('account');
    $this->assertAuthenticated('web');
    Event::assertDispatched(SignedOut::class);
});
