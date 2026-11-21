<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Sunrice\Account\Auth\Verification;
use Sunrice\Account\Emails\EmailDefinition;
use Sunrice\Account\Emails\Emails;
use Sunrice\Account\Facades\Account;
use Sunrice\Account\Notifications\NewRegistrationNotification;
use Sunrice\Account\Notifications\VerifyEmailNotification;
use Sunrice\Account\Notifications\WelcomeNotification;
use Sunrice\Admin\Panel;
use Workbench\App\Models\User;

function signUp(string $email = 'budi@example.com', string $path = '/account/register'): void
{
    test()->post($path, ['name' => 'Budi', 'email' => $email, 'password' => 'Correct-Horse-Battery-9', 'password_confirmation' => 'Correct-Horse-Battery-9']);
}

function staffWith(string $role, string $email): User
{
    $user = siteUser(['name' => ucfirst($role), 'email' => $email]);
    $user->assignRole(Role::findOrCreate($role, 'web'));

    return $user;
}

it('welcomes a new account at registration while emails aren\'t verified', function () {
    Notification::fake();
    accountSettings(['verifyEmail' => false]);

    signUp(path: '/en/account/register');

    $user = User::query()->where('email', 'budi@example.com')->firstOrFail();
    Notification::assertSentTo($user, WelcomeNotification::class, fn (WelcomeNotification $n) => $n->lang === 'en');
});

it('welcomes a new account once its email is verified', function () {
    Notification::fake();

    signUp();
    $user = User::query()->where('email', 'budi@example.com')->firstOrFail();
    Notification::assertNotSentTo($user, WelcomeNotification::class);

    $this->get(Verification::url($user, 'id'));
    Notification::assertSentTo($user, WelcomeNotification::class);
});

it('tells super admins, administrators and the set addresses of a new account, each once', function () {
    Notification::fake();
    $super = staffWith((string) config('sunrice-admin.super_admin_role', 'super-admin'), 'boss@example.com');
    $admin = staffWith('administrator', 'admin@example.com');
    $editor = staffWith('editor', 'editor@example.com');
    accountSettings(['staffRegistrationAddresses' => "office@example.com\nBoss@example.com\nnot-an-email"]);

    signUp();

    Notification::assertSentTo([$super, $admin], NewRegistrationNotification::class, fn (NewRegistrationNotification $n) => $n->name === 'Budi' && $n->email === 'budi@example.com' && $n->lang === 'id');
    Notification::assertNotSentTo($editor, NewRegistrationNotification::class);
    Notification::assertSentOnDemand(NewRegistrationNotification::class, fn ($n, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === ['office@example.com']);
});

it('sends staff emails to the chosen roles, and not at all when switched off', function () {
    Notification::fake();
    staffWith('administrator', 'admin@example.com');
    $editor = staffWith('editor', 'editor@example.com');
    accountSettings(['staffRegistrationRoles' => ['editor']]);

    signUp();
    Notification::assertSentTo($editor, NewRegistrationNotification::class);
    Notification::assertSentTimes(NewRegistrationNotification::class, 1);

    accountSettings(['staffRegistrationEmail' => false]);
    signUp('other@example.com');
    Notification::assertSentTimes(NewRegistrationNotification::class, 1);
});

it('never switches off the security emails', function () {
    accountSettings(['welcomeEmail' => false]);

    expect(Account::emailSettings()->enabled(Emails::VERIFY))->toBeTrue()
        ->and(Account::emailSettings()->enabled(Emails::RESET))->toBeTrue()
        ->and(Account::emailSettings()->enabled(Emails::WELCOME))->toBeFalse()
        ->and((new WelcomeNotification('id'))->via(siteUser()))->toBe([])
        ->and((new VerifyEmailNotification('id'))->via(siteUser(['email' => 'b@example.com'])))->toBe(['mail']);
});

it('uses the subject set for the language, with its placeholders', function () {
    $user = siteUser();
    accountSettings(['welcomeSubject' => 'id: Halo {name} di :site']);
    $site = app(Panel::class)->mailBrand()->name;

    expect((new WelcomeNotification('id'))->toMail($user)->subject)->toBe("Halo Ana di {$site}")
        ->and((new WelcomeNotification('en'))->toMail($user)->subject)->toBe("Welcome to {$site}");
});

it('shows the site\'s brand, in the email\'s language', function () {
    $html = (string) (new WelcomeNotification('id'))->toMail(siteUser())->render();

    expect($html)->toContain(app(Panel::class)->mailBrand()->name)
        ->toContain('Selamat datang, Ana!')
        ->toContain('http://localhost/account');
});

it('uses the app\'s own body for an email', function () {
    $dir = sys_get_temp_dir().'/account-mail-'.bin2hex(random_bytes(4));
    (new Filesystem)->ensureDirectoryExists("{$dir}/emails");
    file_put_contents("{$dir}/emails/account_welcome.blade.php", "<x-mail::message>\nOur own welcome, {{ \$name }} ({{ \$lang }}).\n</x-mail::message>");
    // As resources/views/vendor/account/ is.
    View::prependNamespace('account', $dir);

    try {
        expect((string) (new WelcomeNotification('en'))->toMail(siteUser())->render())->toContain('Our own welcome, Ana (en).');
    } finally {
        (new Filesystem)->deleteDirectory($dir);
    }
});

it('lets plugins register their own emails', function () {
    Account::emails()->register(new EmailDefinition('shop_order_note', 'Order note', 'shop::emails.note', enabledByDefault: false));

    expect(Account::emails()->get('shop_order_note')?->label)->toBe('Order note')
        ->and(Account::emailSettings()->enabled('shop_order_note'))->toBeFalse()
        ->and(Account::emailSettings()->enabled('unknown'))->toBeFalse()
        ->and(array_keys(Account::emails()->all()))->toContain(Emails::WELCOME, Emails::STAFF_REGISTRATION);
});

it('reads a plugin email\'s settings from the plugin', function () {
    Account::emails()->register(new EmailDefinition('shop_order_note', 'Order note', 'shop::emails.note', 'staff', settings: fn (): array => [
        'enabled' => true, 'subject' => "id: Catatan\nen: Note", 'addresses' => ['Shop@Example.com'], 'roles' => ['editor'],
    ]));

    expect(Account::emailSettings()->enabled('shop_order_note'))->toBeTrue()
        ->and(Account::emailSettings()->subject('shop_order_note', 'en'))->toBe('Note')
        ->and(Account::emailSettings()->subject('shop_order_note', 'fr'))->toBeNull()
        ->and(Account::emailSettings()->addresses('shop_order_note'))->toBe(['shop@example.com'])
        ->and(Account::emailSettings()->roles('shop_order_note'))->toBe(['editor']);
});
