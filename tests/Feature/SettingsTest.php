<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Sunrice\Account\Facades\Account;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Settings\SettingsFields;
use Workbench\App\Models\User;

function settingsAdmin(): User
{
    $user = siteUser(['name' => 'Boss', 'email' => 'boss@example.com']);
    $user->assignRole(Role::findOrCreate((string) config('sunrice-admin.super_admin_role', 'super-admin'), 'web'));

    return $user;
}

it('starts from the defaults', function () {
    $settings = Account::settings();

    expect($settings->registration)->toBeTrue()
        ->and($settings->verifyEmail)->toBeTrue()
        ->and($settings->roles())->toBe(['member'])
        ->and($settings->path)->toBe('')
        ->and($settings->loginCode)->toBeTrue();
});

it('saves settings over the defaults, keeping the others', function () {
    accountSettings(['registration' => false]);

    expect(Account::settings()->registration)->toBeFalse()
        ->and(Account::settings()->verifyEmail)->toBeTrue();
});

it('reads a language\'s line of a per-language text', function () {
    expect(AccountSettings::line("id: Halo\nen:  Hello \nfr:", 'en'))->toBe('Hello')
        ->and(AccountSettings::line("id: Halo\nen: Hello", 'EN'))->toBe('Hello')
        ->and(AccountSettings::line("id: Halo\nfr:", 'fr'))->toBe('')
        ->and(AccountSettings::line('', 'id'))->toBe('');
});

it('is Settings → Accounts, behind its own permission', function () {
    expect(app(Panel::class)->getPermissions())->toHaveKey('account.settings.accounts');

    $this->actingAs(settingsAdmin(), 'web')->get('/admin/settings/accounts')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/Edit')->where('section.key', 'accounts')->where('values.registration', true));

    $editor = siteUser(['email' => 'editor@example.com']);
    $editor->assignRole(Role::findOrCreate('editor', 'web'));
    $this->actingAs($editor, 'web')->get('/admin/settings/accounts')->assertForbidden();
});

it('saves from the settings page', function () {
    Role::findOrCreate('member', 'web');

    $this->actingAs(settingsAdmin(), 'web')->put('/admin/settings/accounts', [
        'registration' => false, 'verifyEmail' => true, 'defaultRoles' => ['member'], 'path' => 'members', 'phone' => 'required',
        'loginCode' => true, 'codeMinutes' => 15, 'announcementPosition' => 'below',
        'staffRegistrationAddresses' => "office@example.com\nboss@example.com",
    ])->assertSessionHasNoErrors()->assertRedirect();

    $settings = Account::settings();
    expect($settings->registration)->toBeFalse()
        ->and($settings->path)->toBe('members')
        ->and($settings->phone)->toBe('required')
        ->and($settings->codeMinutes)->toBe(15)
        ->and($settings->staffRegistrationAddresses)->toBe("office@example.com\nboss@example.com")
        ->and(Account::emailSettings()->addresses('account_staff_registration'))->toBe(['office@example.com', 'boss@example.com']);
});

it('never gives new accounts a role that opens the admin', function () {
    Role::findOrCreate('member', 'web');
    $editor = Role::findOrCreate('editor', 'web');
    $editor->givePermissionTo(Permission::findOrCreate('cms.entries.view', 'web'));

    $this->actingAs(settingsAdmin(), 'web')->put('/admin/settings/accounts', ['defaultRoles' => ['member', 'editor'], 'phone' => 'optional', 'codeMinutes' => 10, 'announcementPosition' => 'above'])
        ->assertSessionHasErrors();
    $this->put('/admin/settings/accounts', ['defaultRoles' => ['super-admin'], 'phone' => 'optional', 'codeMinutes' => 10, 'announcementPosition' => 'above'])
        ->assertSessionHasErrors();

    expect(Account::settings()->roles())->toBe(['member']);
});

it('checks the address, the links and Google', function () {
    $this->actingAs(settingsAdmin(), 'web');
    $base = ['phone' => 'optional', 'codeMinutes' => 10, 'announcementPosition' => 'above'];

    $this->put('/admin/settings/accounts', [...$base, 'path' => 'My Account'])->assertSessionHasErrors('path');
    $this->put('/admin/settings/accounts', [...$base, 'path' => 'admin'])->assertSessionHasErrors('path');
    $this->put('/admin/settings/accounts', [...$base, 'redirectAfterLogin' => '//evil.test'])->assertSessionHasErrors('redirectAfterLogin');
    $this->put('/admin/settings/accounts', [...$base, 'redirectAfterLogin' => 'https://evil.test'])->assertSessionHasErrors('redirectAfterLogin');
    $this->put('/admin/settings/accounts', [...$base, 'termsUrl' => 'javascript:alert(1)'])->assertSessionHasErrors('termsUrl');
    $this->put('/admin/settings/accounts', [...$base, 'codeMinutes' => 90])->assertSessionHasErrors('codeMinutes');

    config(['services.google' => null, 'sunrice-account.google' => ['client_id' => null, 'client_secret' => null]]);
    $this->put('/admin/settings/accounts', [...$base, 'googleLogin' => true])->assertSessionHasErrors('googleLogin');

    $this->put('/admin/settings/accounts', [...$base, 'path' => 'members', 'redirectAfterLogin' => '/welcome?from=login', 'termsUrl' => 'https://example.com/terms'])
        ->assertSessionHasNoErrors();
    expect(Account::settings()->termsUrl)->toBe('https://example.com/terms');
});

it('leaves the Settings menu to a plugin that shows the settings itself', function () {
    Account::settingsShownBy('shop');

    expect(Account::settingsOwner())->toBe('shop')
        ->and(AccountSettings::available())->toBeFalse();
    $this->actingAs(settingsAdmin(), 'web')->get('/admin/settings/accounts')->assertNotFound();

    // The plugin draws and saves the same fields on its own page.
    $fields = SettingsFields::make(AccountSettings::class, AccountSettings::form(new Form));
    expect($fields->props()['values'])->toHaveKey('registration');
    app(SaveSettings::class)->handle(app(AccountSettings::class), $fields->validate(['registration' => false, 'phone' => 'off', 'codeMinutes' => 10, 'announcementPosition' => 'above']));
    expect(Account::settings()->registration)->toBeFalse()
        ->and(Account::settings()->phone)->toBe('off');
});

it('gives the form\'s sections by key', function () {
    expect(array_keys(AccountSettings::sections()))->toBe(['general', 'sign-in', 'announcement', 'emails']);
});

it('sends staff emails only to roles that open the admin', function () {
    Role::findOrCreate('member', 'web');
    $editor = Role::findOrCreate('editor', 'web');
    $editor->givePermissionTo(Permission::findOrCreate(Panel::ACCESS, 'web'));

    expect(array_keys(AccountSettings::staffRoles()))->toBe(['editor', 'super-admin']);
    $this->actingAs(settingsAdmin(), 'web')->put('/admin/settings/accounts', ['staffRegistrationRoles' => ['member'], 'phone' => 'optional', 'codeMinutes' => 10, 'announcementPosition' => 'tab'])
        ->assertSessionHasErrors('staffRegistrationRoles.0');
});

it('refuses an account address where the site already has pages, and an announcement without a message', function () {
    Route::get('/members/offers', fn () => 'offers');
    app('router')->getRoutes()->refreshNameLookups();
    $admin = settingsAdmin();

    $this->actingAs($admin, 'web')->put('/admin/settings/accounts', ['path' => 'members', 'phone' => 'optional', 'codeMinutes' => 10, 'announcementPosition' => 'tab'])
        ->assertSessionHasErrors('path');
    $this->put('/admin/settings/accounts', ['announcement' => true, 'announcementMessage' => '', 'phone' => 'optional', 'codeMinutes' => 10, 'announcementPosition' => 'tab'])
        ->assertSessionHasErrors('announcementMessage');
});
