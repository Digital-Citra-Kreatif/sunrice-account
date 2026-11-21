<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Sunrice\Account\Notifications\ResetPasswordNotification;

const NEW_PASSWORD = 'Another-Strong-Pass-42';

it('emails a reset link to the account, in the language of the page', function () {
    Notification::fake();
    $user = siteUser();

    $this->get('/en/account/forgot-password')->assertOk()->assertSee('Forgot your password?');
    $this->post('/en/account/forgot-password', ['email' => 'Ana@example.com'])->assertSessionHas('account.status');

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $n) use ($user) {
        $mail = $n->toMail($user);

        return $n->lang === 'en' && str_starts_with((string) $mail->actionUrl, 'http://localhost/en/account/reset-password/');
    });
});

it('answers the same when the email has no account', function () {
    Notification::fake();

    $this->post('/account/forgot-password', ['email' => 'who@example.com'])
        ->assertSessionHas('account.status', 'Jika email ini memiliki akun, tautan untuk memilih kata sandi baru sedang dikirim.');
    Notification::assertNothingSent();
});

it('sets a new password from the link', function () {
    $user = siteUser(['email_verified_at' => null]);
    $token = Password::broker()->createToken($user);

    $this->get("/account/reset-password/{$token}?email=ana@example.com")->assertOk()->assertSee($token);
    $this->post('/account/reset-password', ['token' => $token, 'email' => 'ana@example.com', 'password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD])
        ->assertRedirect('/account/login')->assertSessionHas('account.status');

    $user->refresh();
    expect(Hash::check(NEW_PASSWORD, $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => NEW_PASSWORD])->assertRedirect('/account');
});

it('refuses an invalid link and a weak password', function () {
    $user = siteUser();
    $token = Password::broker()->createToken($user);

    $this->post('/account/reset-password', ['token' => 'wrong', 'email' => 'ana@example.com', 'password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD])
        ->assertSessionHasErrors('email');
    $this->post('/account/reset-password', ['token' => $token, 'email' => 'ana@example.com', 'password' => 'weak', 'password_confirmation' => 'weak'])
        ->assertSessionHasErrors('password');

    expect(Hash::check('secret-pass', $user->fresh()->password))->toBeTrue();
});

it('slows down repeated reset requests', function () {
    Notification::fake();
    foreach (range(1, 3) as $i) {
        $this->post('/account/forgot-password', ['email' => 'ana@example.com']);
    }

    $this->post('/account/forgot-password', ['email' => 'ana@example.com'])->assertSessionHasErrors('email');
});
