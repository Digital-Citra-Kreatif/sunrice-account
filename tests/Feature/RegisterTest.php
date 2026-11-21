<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Sunrice\Account\Events\Registered;
use Sunrice\Account\Events\Registering;
use Sunrice\Account\Facades\Account;
use Sunrice\Account\Notifications\ExistingAccountNotification;
use Workbench\App\Models\User;

const STRONG = 'Correct-Horse-Battery-9';

function registration(array $overrides = []): array
{
    return [...['name' => 'Budi', 'email' => 'Budi@Example.com', 'phone' => '0812 3456 7890', 'password' => STRONG, 'password_confirmation' => STRONG, 'accepts_marketing' => '1'], ...$overrides];
}

it('creates an account with the default roles and a profile, and signs it in', function () {
    Event::fake([Registered::class]);

    $this->post('/account/register', registration())->assertRedirect('/account')->assertSessionHas('account.status');

    $user = User::query()->where('email', 'budi@example.com')->firstOrFail();
    expect($user->name)->toBe('Budi')
        ->and(Hash::check(STRONG, $user->password))->toBeTrue()
        ->and($user->hasRole('member'))->toBeTrue()
        ->and($user->can('sunrice.access'))->toBeFalse();
    $profile = Account::profile($user);
    expect($profile->phone)->toBe('0812 3456 7890')
        ->and($profile->locale)->toBe('id')
        ->and($profile->accepts_marketing)->toBeTrue()
        ->and($profile->marketing_consent_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user, 'account');
    $this->assertGuest('web');
    Event::assertDispatched(Registered::class, fn (Registered $e) => $e->user->getAuthIdentifier() === $user->id && $e->locale === 'id');
});

it('registers in the language of the address', function () {
    $this->get('/en/account/register')->assertOk()->assertSee('Create an account');
    $this->post('/en/account/register', registration())->assertRedirect('/en/account');

    expect(Account::profile(User::query()->firstOrFail())->locale)->toBe('en');
});

it('never reveals that an email has an account', function () {
    Notification::fake();
    $existing = siteUser(['email' => 'budi@example.com']);

    $this->post('/account/register', registration())->assertRedirect('/account/login')->assertSessionHas('account.status', 'Periksa email Anda untuk melanjutkan.');

    expect(User::query()->count())->toBe(1);
    $this->assertGuest('account');
    Notification::assertSentTo($existing, ExistingAccountNotification::class);
});

it('validates what the settings ask for', function () {
    Account::settings()->saveSection('general', ['phone' => 'required', 'terms_url' => 'https://example.com/terms']);

    $this->get('/account/register')->assertSee('https://example.com/terms');
    $this->post('/account/register', registration(['phone' => '']))->assertSessionHasErrors(['phone', 'terms']);
    $this->post('/account/register', registration(['password_confirmation' => 'other']))->assertSessionHasErrors('password');
    $this->post('/account/register', registration(['password' => 'short', 'password_confirmation' => 'short', 'terms' => '1']))->assertSessionHasErrors('password');

    expect(User::query()->count())->toBe(0);
});

it('lets listeners change the registration or refuse it', function () {
    Event::listen(Registering::class, function (Registering $event): void {
        if (str_ends_with((string) $event->data['email'], '@spam.test')) {
            throw ValidationException::withMessages(['email' => 'No.']);
        }
        $event->data['name'] = mb_strtoupper((string) $event->data['name']);
    });

    $this->post('/account/register', registration(['email' => 'bot@spam.test']))->assertSessionHasErrors('email');
    $this->post('/account/register', registration());

    expect(User::query()->pluck('name')->all())->toBe(['BUDI']);
});

it('is closed when registration is off', function () {
    Account::settings()->saveSection('general', ['registration' => false]);

    $this->get('/account/register')->assertNotFound();
    $this->post('/account/register', registration())->assertNotFound();
    $this->get('/account/login')->assertDontSee('/account/register');
});

it('lets one address register a few accounts an hour', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/account/register', registration(['email' => "budi{$i}@example.com"]));
        auth('account')->logout();
    }

    $this->post('/account/register', registration(['email' => 'budi6@example.com']))->assertSessionHasErrors('email');
    expect(User::query()->count())->toBe(5);
});
