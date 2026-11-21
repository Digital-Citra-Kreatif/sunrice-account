<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Sunrice\Account\Events\DeletionRequested;
use Sunrice\Account\Events\PasswordChanged;
use Sunrice\Account\Events\ProfileUpdated;
use Sunrice\Account\Events\ProfileUpdating;
use Sunrice\Account\Facades\Account;
use Sunrice\Account\Notifications\DeletionRequestNotification;
use Sunrice\Account\Notifications\VerifyEmailNotification;

const NEW_PASS = 'Brand-New-Password-77';

beforeEach(function () {
    Notification::fake();
    $this->user = siteUser();
    $this->actingAs($this->user, 'account');
});

it('shows the profile and saves the details', function () {
    Event::fake([ProfileUpdated::class]);
    $this->get('/account/profile')->assertOk()->assertSee('ana@example.com');

    $this->put('/account/profile', ['name' => 'Ana Putri', 'email' => 'ana@example.com', 'phone' => '0812', 'accepts_marketing' => '1'])
        ->assertRedirect()->assertSessionHas('account.status', 'Profil Anda sudah disimpan.');

    $profile = Account::profile($this->user);
    expect($this->user->fresh()->name)->toBe('Ana Putri')
        ->and($profile->phone)->toBe('0812')
        ->and($profile->accepts_marketing)->toBeTrue()
        ->and($profile->marketing_consent_at)->not->toBeNull();
    Event::assertDispatched(ProfileUpdated::class, fn (ProfileUpdated $e) => $e->changed === ['name', 'phone', 'accepts_marketing']);
});

it('needs the current password to change the email, then verifies the new one', function () {
    $this->put('/account/profile', ['name' => 'Ana', 'email' => 'new@example.com'])->assertSessionHasErrors('current_password');
    expect($this->user->fresh()->email)->toBe('ana@example.com');

    $this->put('/account/profile', ['name' => 'Ana', 'email' => 'New@Example.com', 'current_password' => 'secret-pass'])
        ->assertSessionHas('account.status', fn ($status) => str_contains((string) $status, 'email baru'));

    $user = $this->user->fresh();
    expect($user->email)->toBe('new@example.com')->and($user->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('refuses an email another account has', function () {
    siteUser(['email' => 'taken@example.com']);

    $this->put('/account/profile', ['name' => 'Ana', 'email' => 'taken@example.com', 'current_password' => 'secret-pass'])->assertSessionHasErrors('email');
});

it('lets listeners change the profile or refuse it', function () {
    Event::listen(ProfileUpdating::class, function (ProfileUpdating $event): void {
        if ($event->data['name'] === 'Admin') {
            throw ValidationException::withMessages(['name' => 'Not that name.']);
        }
        $event->data['name'] = trim((string) $event->data['name']).'!';
    });

    $this->put('/account/profile', ['name' => 'Admin', 'email' => 'ana@example.com'])->assertSessionHasErrors('name');
    $this->put('/account/profile', ['name' => 'Ana', 'email' => 'ana@example.com']);

    expect($this->user->fresh()->name)->toBe('Ana!');
});

it('changes the password with the current one', function () {
    Event::fake([PasswordChanged::class]);
    $token = $this->user->remember_token;

    $this->put('/account/profile/password', ['current_password' => 'wrong', 'password' => NEW_PASS, 'password_confirmation' => NEW_PASS])->assertSessionHasErrors('current_password');
    $this->put('/account/profile/password', ['current_password' => 'secret-pass', 'password' => 'weak', 'password_confirmation' => 'weak'])->assertSessionHasErrors('password');
    $this->put('/account/profile/password', ['current_password' => 'secret-pass', 'password' => NEW_PASS, 'password_confirmation' => NEW_PASS])->assertSessionHas('account.status');

    $user = $this->user->fresh();
    expect(Hash::check(NEW_PASS, $user->password))->toBeTrue()->and($user->remember_token)->not->toBe($token);
    Event::assertDispatched(PasswordChanged::class);
});

it('lets an account made without a password choose one', function () {
    Account::profile($this->user)->forceFill(['password_set' => false])->save();
    $this->get('/account/profile')->assertSee('Pilih kata sandi');

    $this->put('/account/profile/password', ['password' => NEW_PASS, 'password_confirmation' => NEW_PASS])->assertSessionHasNoErrors();

    expect(Account::profile($this->user->fresh())->password_set)->toBeTrue();
});

it('asks for the account to be deleted, once, and tells staff', function () {
    Event::fake([DeletionRequested::class]);
    $admin = siteUser(['email' => 'admin@example.com']);
    $admin->assignRole(Role::findOrCreate('administrator', 'web'));

    $this->post('/account/profile/delete')->assertSessionHasErrors('confirm');
    $this->post('/account/profile/delete', ['confirm' => '1'])->assertSessionHas('account.status');
    $this->post('/account/profile/delete', ['confirm' => '1']);

    expect(Account::profile($this->user)->deletion_requested_at)->not->toBeNull();
    Event::assertDispatchedTimes(DeletionRequested::class, 1);
    $this->get('/account/profile')->assertDontSee('Minta penghapusan akun');
});

it('emails staff a deletion request', function () {
    $admin = siteUser(['email' => 'admin@example.com']);
    $admin->assignRole(Role::findOrCreate('administrator', 'web'));

    $this->post('/account/profile/delete', ['confirm' => '1']);

    Notification::assertSentTo($admin, DeletionRequestNotification::class, fn (DeletionRequestNotification $n) => $n->email === 'ana@example.com');
});

it('can turn deletion requests off', function () {
    Account::settings()->saveSection('general', ['deletion_requests' => false]);

    $this->get('/account/profile')->assertDontSee('Hapus akun saya');
    $this->post('/account/profile/delete', ['confirm' => '1'])->assertNotFound();
});

it('is for signed-in accounts only', function () {
    auth('account')->logout();

    $this->get('/account/profile')->assertRedirect('/account/login');
    $this->put('/account/profile', ['name' => 'X', 'email' => 'x@example.com'])->assertRedirect('/account/login');
});
