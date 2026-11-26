<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Sunrice\Account\Facades\Account;
use Sunrice\Account\Notifications\LoginCodeNotification;
use Sunrice\Account\Notifications\VerifyEmailNotification;
use Workbench\App\Models\User;

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

it('comes back to the page a sign-in link was on', function () {
    siteUser();

    $this->get('/account/login?return=/blog/first-post?page=2')->assertOk();
    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertRedirect('/blog/first-post?page=2');

    // Already signed in: straight on, without the sign-in page.
    $this->get('/account/login?return=/blog/second-post')->assertRedirect('/blog/second-post');
    $this->get('/account/register?return=/blog/second-post')->assertRedirect('/blog/second-post');
});

it('makes sign-in links that come back', function () {
    $this->get('/account/login'); // a request to build on
    expect(Account::signInUrl('/checkout'))->toEndWith('/account/login?return=%2Fcheckout')
        ->and(Account::registerUrl('/blog/a?page=2'))->toEndWith('/account/register?return=%2Fblog%2Fa%3Fpage%3D2')
        ->and(Account::signInUrl('https://evil.example/x'))->toEndWith('/account/login')
        // From the sign-in page itself: nowhere to come back to.
        ->and(Account::signInUrl())->toEndWith('/account/login');
});

it('never follows a return address to another site', function () {
    siteUser();

    foreach (['https://evil.example/steal', '//evil.example', '/\\evil.example', 'javascript:alert(1)'] as $return) {
        $this->get('/account/login?return='.urlencode($return))->assertOk();
        $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertRedirect('/account');
        $this->get('/account/login?return='.urlencode($return))->assertRedirect('/account');
        $this->post('/account/logout');
    }
});

it('carries where a new account was going in its verification link, for another browser', function () {
    Notification::fake();
    $this->get('/account/register?return=/checkout')->assertOk();
    $this->post('/account/register', ['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'Correct-Horse-Battery-9', 'password_confirmation' => 'Correct-Horse-Battery-9']);
    $user = User::query()->where('email', 'budi@example.com')->sole();
    $link = '';
    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $n) use ($user, &$link) {
        $link = $n->toMail($user)->actionUrl;

        return true;
    });
    expect($link)->toContain('return=');

    // Opened elsewhere: no session knows about the checkout, the link does.
    $this->flushSession();
    $this->get($link)->assertRedirect('/checkout');
    $this->assertAuthenticatedAs($user, 'account');
});

it('signs in with the password on Enter, and asks for a code when none is typed', function () {
    Notification::fake();
    siteUser();

    // The form's first submit button is the one Enter presses: it posts to the password sign-in.
    $html = $this->get('/account/login')->assertOk()->getContent();
    expect(strpos($html, 'formnovalidate tabindex="-1"'))->toBeLessThan(strpos($html, 'account/login/code'));

    // Only the email typed: on to the code.
    $this->post('/account/login', ['email' => 'ana@example.com'])->assertRedirect('/account/login/code');
    Notification::assertSentTimes(LoginCodeNotification::class, 1);
    $this->assertGuest('account');

    // With the password: signed in, no code.
    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertRedirect('/account');
    $this->assertAuthenticated('account');
    Notification::assertSentTimes(LoginCodeNotification::class, 1);
});

it('still asks for the password when codes are off', function () {
    accountSettings(['loginCode' => false]);
    siteUser();

    $this->post('/account/login', ['email' => 'ana@example.com'])->assertSessionHasErrors('password');
});
