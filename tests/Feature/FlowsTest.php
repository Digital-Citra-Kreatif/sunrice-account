<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Sunrice\Account\Facades\Account;
use Sunrice\Account\Notifications\LoginCodeNotification;
use Sunrice\Account\Notifications\VerifyEmailNotification;

it('asks a signed-in account to verify, says where the link went, and sends it again', function () {
    Notification::fake();
    Route::middleware(['web', 'sunrice.account.auth', 'sunrice.account.verified'])->get('/members', fn () => 'members');
    $user = siteUser(['email_verified_at' => null]);
    $this->actingAs($user, 'account');

    $this->get('/members')->assertRedirect(Account::url('sunrice.account.verification.notice'));
    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);
    $this->get('/account/verify')->assertOk()->assertSee('ana@example.com')->assertSee('Kirim ulang tautan')->assertDontSee('name="email"', false);

    $this->travel(2)->minutes();
    $this->post('/account/verify')->assertSessionHasNoErrors();
    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 2);
});

it('sends a verified account from the verify page to My Account', function () {
    $this->actingAs(siteUser(), 'account')->get('/account/verify')->assertRedirect(Account::url('sunrice.account.dashboard'));
});

it('sends a new code after a minute, and says why when it\'s too soon', function () {
    Notification::fake();
    accountSettings(['loginCode' => true]);
    $user = siteUser();

    $this->post('/account/login/code', ['email' => 'ana@example.com', 'remember' => '1'])->assertRedirect('/account/login/code');
    $this->get('/account/login/code')->assertSee('ana@example.com')->assertSee('name="remember" value="1" checked', false);
    $this->from('/account/login/code')->post('/account/login/code', ['email' => 'ana@example.com'])->assertSessionHasErrors('email');
    $this->get('/account/login/code')->assertSee('account-field__error', false);

    $this->travel(61)->seconds();
    $this->post('/account/login/code', ['email' => 'ana@example.com'])->assertSessionHasNoErrors();
    Notification::assertSentToTimes($user, LoginCodeNotification::class, 2);
});

it('asks for the email once on the sign-in page, with the code as a second button', function () {
    accountSettings(['loginCode' => true]);

    $html = (string) $this->get('/account/login')->getContent();
    expect(substr_count($html, 'name="email"'))->toBe(1)
        ->and($html)->toContain('formaction="http://localhost/account/login/code"');
});

it('keeps an unticked checkbox unticked after an error', function () {
    $user = siteUser();
    Account::profile($user)->forceFill(['accepts_marketing' => true])->save();
    accountSettings(['marketingConsent' => true]);
    $this->actingAs($user, 'account');

    $this->from('/account/profile')->put('/account/profile', ['name' => '', 'email' => 'ana@example.com'])->assertSessionHasErrors('name');
    $this->get('/account/profile')->assertDontSee('name="accepts_marketing" value="1" checked', false);
});

it('names the fields in messages', function () {
    $this->post('/account/register', ['name' => '', 'email' => 'budi@example.com', 'password' => 'Correct-Horse-Battery-9', 'password_confirmation' => 'x']);

    expect(session('errors')->first('name'))->toContain('Nama')
        ->and(session('errors')->first('password'))->toContain('Kata sandi');
});

it('takes a new account where it was going', function () {
    accountSettings(['verifyEmail' => false]);
    $this->get('/account/profile?tab=2');

    $this->post('/account/register', ['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'Correct-Horse-Battery-9', 'password_confirmation' => 'Correct-Horse-Battery-9'])
        ->assertRedirect('http://localhost/account/profile?tab=2');
});

it('reports a reset link that no longer works above the form, with a way to a new one', function () {
    siteUser();
    $this->from('/account/reset-password/bad')->post('/account/reset-password', ['token' => 'bad', 'email' => 'ana@example.com', 'password' => 'Correct-Horse-Battery-9', 'password_confirmation' => 'Correct-Horse-Battery-9'])
        ->assertSessionHasErrors('token');
    $this->get('/account/reset-password/bad')->assertSee('Minta tautan baru')->assertSee('http://localhost/account/forgot-password');
});
