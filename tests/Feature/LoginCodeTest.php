<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Sunrice\Account\Notifications\LoginCodeNotification;
use Sunrice\Account\Notifications\WelcomeNotification;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Panel;
use Workbench\App\Models\User;

beforeEach(function () {
    Notification::fake();
    accountSettings(['loginCode' => true]);
});

/** Asks for a code for the email and returns the one emailed. */
function requestCode(string $email = 'ana@example.com'): string
{
    test()->post('/account/login/code', ['email' => $email])->assertRedirect('/account/login/code');
    $code = '';
    Notification::assertSentTo(User::query()->where('email', $email)->firstOrFail(), LoginCodeNotification::class, function (LoginCodeNotification $n) use (&$code) {
        $code = $n->code;

        return true;
    });

    return $code;
}

it('is off unless the settings turn it on', function () {
    accountSettings(['loginCode' => false]);

    $this->post('/account/login/code', ['email' => 'ana@example.com'])->assertNotFound();
    $this->get('/account/login')->assertDontSee('/account/login/code');
});

it('signs in with the emailed code, which verifies the email', function () {
    $user = siteUser(['email_verified_at' => null]);
    $this->get('/en/account/login')->assertSee('Email me a sign-in code');

    $code = requestCode();
    expect($code)->toMatch('/^\d{6}$/');
    $this->get('/account/login/code')->assertOk()->assertSee('Masukkan kode masuk Anda');
    $this->post('/account/login/code/check', ['code' => $code])->assertRedirect('/account');

    $this->assertAuthenticatedAs($user, 'account');
    expect($user->fresh()->email_verified_at)->not->toBeNull();
    Notification::assertSentTo($user, WelcomeNotification::class);
    // Used up.
    auth('account')->logout();
    $this->post('/account/login/code/check', ['code' => $code])->assertSessionHasErrors('code');
});

it('answers the same for an email without an account', function () {
    $this->post('/account/login/code', ['email' => 'who@example.com'])->assertRedirect('/account/login/code')->assertSessionHas('account.status');

    Notification::assertNothingSent();
    $this->post('/account/login/code/check', ['code' => '123456'])->assertSessionHasErrors('code');
    $this->assertGuest('account');
});

/** Every failure reads the same: an email with no account gets it too. */
const INCORRECT_CODE = 'Kode ini tidak berlaku. Periksa lagi, atau minta kode baru: kode hanya berlaku sekali, selama beberapa menit, di browser yang memintanya.';

it('refuses wrong codes, and every try for a minute after five', function () {
    siteUser();
    $code = requestCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 4) as $i) {
        $this->post('/account/login/code/check', ['code' => $wrong])->assertSessionHasErrors('code');
    }
    expect(session('errors')->first('code'))->toBe(INCORRECT_CODE);
    $this->post('/account/login/code/check', ['code' => $wrong]);
    $this->post('/account/login/code/check', ['code' => $code])->assertSessionHasErrors('code');

    expect(session('errors')->first('code'))->toBe(INCORRECT_CODE);
    $this->assertGuest('account');
});

it('refuses an expired code, and a code used in another browser', function () {
    siteUser();
    $code = requestCode();

    $this->withHeader('User-Agent', 'Another browser')->post('/account/login/code/check', ['code' => $code])->assertSessionHasErrors('code');
    expect(session('errors')->first('code'))->toBe(INCORRECT_CODE);

    $this->travel(11)->minutes();
    $this->post('/account/login/code/check', ['code' => $code])->assertSessionHasErrors('code');
    expect(session('errors')->first('code'))->toBe(INCORRECT_CODE);
    $this->assertGuest('account');
});

it('sends at most one code a minute to an email', function () {
    siteUser();
    requestCode();

    $this->post('/account/login/code', ['email' => 'ana@example.com'])->assertSessionHasErrors('email');
    Notification::assertSentTimes(LoginCodeNotification::class, 1);
});

it('sends no code to staff while the site shares the admin\'s guard', function () {
    config(['sunrice-account.auth.guard' => 'web']);
    app(SyncPermissions::class)->handle();
    siteUser()->assignRole(Role::findOrCreate('staff', 'web')->givePermissionTo(Panel::ACCESS));

    $this->post('/account/login/code', ['email' => 'ana@example.com'])->assertRedirect('/account/login/code');

    Notification::assertNothingSent();
});

it('takes back an unverified account from whoever made it, when a code proves its email', function () {
    siteUser(['password' => 'attacker-pass', 'email_verified_at' => null]);

    $this->post('/account/login/code/check', ['code' => requestCode()])->assertRedirect();
    $this->assertAuthenticated('account');
    $this->post('/account/logout');

    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'attacker-pass'])->assertSessionHasErrors('email');
});

it('answers the same for an email without an account, however many tries', function () {
    $this->post('/account/login/code', ['email' => 'nobody@example.com']);
    foreach (range(1, 6) as $i) {
        $this->post('/account/login/code/check', ['code' => '000000'])->assertSessionHasErrors('code');
        expect(session('errors')->first('code'))->toBe(INCORRECT_CODE);
    }
});

it('sends at most five codes an hour to an email, from any IP', function () {
    siteUser();
    foreach (range(1, 5) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])->post('/account/login/code', ['email' => 'ana@example.com'])->assertSessionHasNoErrors();
    }
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->post('/account/login/code', ['email' => 'ana@example.com'])->assertSessionHasErrors('email');
    Notification::assertSentToTimes(User::query()->sole(), LoginCodeNotification::class, 5);
});
