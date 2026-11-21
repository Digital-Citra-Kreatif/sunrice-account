<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Sunrice\Account\Auth\Verification;
use Sunrice\Account\Facades\Account;
use Sunrice\Account\Notifications\NewRegistrationNotification;
use Sunrice\Admin\Security\AccountGuard;

it('signs in from a verification link once, and never over someone signed in', function () {
    Notification::fake();
    $ana = siteUser(['email_verified_at' => null]);
    $url = Verification::url($ana, 'id');

    // Someone else, signed in, opens Ana's link: they stay themselves.
    $bo = siteUser(['name' => 'Bo', 'email' => 'bo@example.com']);
    $this->actingAs($bo, 'account')->get($url)->assertRedirect('/account/login');
    $this->assertAuthenticatedAs($bo, 'account');
    expect(Verification::verified($ana->fresh()))->toBeTrue();
    $this->post('/account/logout');

    // Already verified: the link no longer signs in.
    $this->get($url)->assertRedirect('/account/login');
    $this->assertGuest('account');
});

it('signs out a locked account\'s site session, and sessions from before "Sign out everywhere"', function () {
    $user = siteUser();
    $this->actingAs($user, 'account')->get('/account/profile')->assertOk();

    app(AccountGuard::class)->lock($user);
    $this->get('/account/profile')->assertRedirect('/account/login');
    $this->assertGuest('account');

    app(AccountGuard::class)->unlock($user);
    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass']);
    $this->get('/account/profile')->assertOk();
    $this->travel(2)->seconds();
    app(AccountGuard::class)->signOutEverywhere($user, false);
    $this->get('/account/profile')->assertRedirect('/account/login');
});

it('ends other sessions when the password changes, keeping this one', function () {
    $user = siteUser();
    // Another device signed in earlier.
    $this->actingAs($user, 'account')->withSession([AccountGuard::LOGIN_AT => now()->getTimestamp()]);
    $this->travel(2)->seconds();

    $this->put('/account/profile/password', ['current_password' => 'secret-pass', 'password' => 'New-Correct-Horse-9', 'password_confirmation' => 'New-Correct-Horse-9'])->assertSessionHasNoErrors();
    $this->get('/account/profile')->assertOk();

    // A session that signed in before the change no longer gets in.
    $this->withSession([AccountGuard::LOGIN_AT => now()->subMinute()->getTimestamp()])->get('/account/profile')->assertRedirect('/account/login');
});

it('checks the current password before saying an address is taken, and stops after five tries', function () {
    siteUser(['name' => 'Bo', 'email' => 'taken@example.com']);
    $this->actingAs(siteUser(), 'account');

    $this->put('/account/profile', ['name' => 'Ana', 'email' => 'taken@example.com', 'current_password' => 'wrong'])->assertSessionHasErrors('current_password')->assertSessionDoesntHaveErrors('email');
    $this->put('/account/profile', ['name' => 'Ana', 'email' => 'TAKEN@example.com', 'current_password' => 'secret-pass'])->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->not->toContain('taken');

    foreach (range(1, 3) as $i) {
        $this->put('/account/profile', ['name' => 'Ana', 'email' => 'taken@example.com', 'current_password' => 'secret-pass']);
    }
    $this->put('/account/profile', ['name' => 'Ana', 'email' => 'free@example.com', 'current_password' => 'secret-pass'])->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('detik');
});

it('marks a new address unverified even while emails aren\'t verified', function () {
    accountSettings(['verifyEmail' => false]);
    $user = siteUser();
    $this->actingAs($user, 'account')->put('/account/profile', ['name' => 'Ana', 'email' => 'new@example.com', 'current_password' => 'secret-pass'])->assertSessionHasNoErrors();

    expect(Verification::verified($user->fresh()))->toBeFalse();
});

it('stops password guesses for one email from many IPs', function () {
    siteUser();
    foreach (range(1, 20) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.'.intdiv($i, 4).'.'.$i])->post('/account/login', ['email' => 'ana@example.com', 'password' => 'wrong']);
    }
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertSessionHasErrors('email');
    $this->assertGuest('account');
});

it('shows visitors\' names in staff emails as text, never as links', function () {
    $mail = (new NewRegistrationNotification('[Approve here](https://evil.example/x)', 'a_b@example.com', 'en'))->toMail(new AnonymousNotifiable);

    expect((string) $mail->render())->not->toContain('href="https://evil.example/x"')->toContain('[Approve here](https://evil.example/x)');
});

it('sends a guest back only to an address on this site', function () {
    Route::middleware('web')->get('/members-only', fn (Request $request) => Account::redirectToSignIn($request->query('return')));
    siteUser();

    foreach (['https://evil.test\\@localhost/x', '//evil.test/x', 'https://evil.test/x', 'javascript:alert(1)'] as $return) {
        $this->get('/members-only?return='.urlencode($return));
        $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertRedirect('http://localhost/members-only?return='.urlencode($return));
        $this->post('/account/logout');
    }
    $this->get('/members-only?return='.urlencode('/checkout?step=2'));
    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertRedirect('http://localhost/checkout?step=2');
});
