<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Spatie\Permission\Models\Role;
use Sunrice\Account\Facades\Account;
use Sunrice\Account\Notifications\NewRegistrationNotification;
use Sunrice\Account\Notifications\WelcomeNotification;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Panel;
use Workbench\App\Models\User;

beforeEach(function () {
    Notification::fake();
    config(['services.google' => ['client_id' => 'client', 'client_secret' => 'secret']]);
    Account::settings()->saveSection('general', ['google_login' => true]);
});

/** Google answers the callback with this user. */
function googleReturns(string $email = 'gita@example.com', bool $verified = true, string $id = 'g-1'): void
{
    $google = (new GoogleUser)->setRaw(['email_verified' => $verified])->map(['id' => $id, 'name' => 'Gita', 'email' => $email, 'avatar' => 'https://example.com/a.png']);
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($google);
    $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('is off unless the settings turn it on and Google is set up', function () {
    Account::settings()->saveSection('general', ['google_login' => false]);
    $this->get('/account/login/google')->assertNotFound();

    Account::settings()->saveSection('general', ['google_login' => true]);
    config(['services.google' => [], 'sunrice-account.google' => ['client_id' => null, 'client_secret' => null]]);
    $this->get('/account/login/google')->assertNotFound();
    $this->get('/account/login')->assertDontSee('/account/login/google');
});

it('goes to Google and back in the visitor\'s language', function () {
    googleReturns();

    $this->get('/en/account/login')->assertSee('Sign in with Google');
    $this->get('/en/account/login/google')->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    $this->get('/account/login/google/callback')->assertRedirect('/en/account');
});

it('creates a verified account for a new Google user, and signs it in', function () {
    googleReturns();
    $admin = siteUser(['email' => 'admin@example.com']);
    $admin->assignRole(Role::findOrCreate('administrator', 'web'));

    $this->get('/account/login/google/callback')->assertRedirect('/account');

    $user = User::query()->where('email', 'gita@example.com')->firstOrFail();
    expect($user->name)->toBe('Gita')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole('member'))->toBeTrue()
        ->and(Account::profile($user)->password_set)->toBeFalse()
        ->and(DB::table('account_social_accounts')->where('provider_id', 'g-1')->value('user_id'))->toBe($user->id);
    $this->assertAuthenticatedAs($user, 'account');
    Notification::assertSentTo($user, WelcomeNotification::class);
    Notification::assertSentTo($admin, NewRegistrationNotification::class);
});

it('links an existing account by its verified email, then by the link', function () {
    $user = siteUser(['email' => 'gita@example.com', 'email_verified_at' => null]);
    googleReturns();

    $this->get('/account/login/google/callback')->assertRedirect('/account');
    $this->assertAuthenticatedAs($user, 'account');
    expect($user->fresh()->email_verified_at)->not->toBeNull()
        ->and(User::query()->count())->toBe(1);

    // Later, with another email at Google: the link still finds the account.
    auth('account')->logout();
    googleReturns('gita.new@example.com');
    $this->get('/account/login/google/callback')->assertRedirect('/account');
    $this->assertAuthenticatedAs($user, 'account');
});

it('creates no account for an unverified Google email, nor while registration is closed', function () {
    googleReturns(verified: false);
    $this->get('/account/login/google/callback')->assertRedirect('/account/login')->assertSessionHasErrors('email');

    Account::settings()->saveSection('general', ['google_login' => true, 'registration' => false]);
    googleReturns();
    $this->get('/account/login/google/callback')->assertRedirect('/account/login')->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(0);
    $this->assertGuest('account');
});

it('refuses staff while the site shares the admin\'s guard', function () {
    config(['sunrice-account.auth.guard' => 'web']);
    app(SyncPermissions::class)->handle();
    siteUser(['email' => 'gita@example.com'])->assignRole(Role::findOrCreate('staff', 'web')->givePermissionTo(Panel::ACCESS));
    googleReturns();

    $this->get('/account/login/google/callback')->assertRedirect('/account/login')->assertSessionHasErrors('email');
    $this->assertGuest('web');
});

it('says so when Google fails', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andThrow(new RuntimeException('invalid state'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/account/login/google/callback')->assertRedirect('/account/login')->assertSessionHasErrors('email');
});
