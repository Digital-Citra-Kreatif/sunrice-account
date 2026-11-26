<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Sunrice\Account\Admin\AccountResource;
use Sunrice\Account\Admin\Accounts;
use Sunrice\Account\Admin\AccountUserResource;
use Sunrice\Account\Auth\Actions\SignInWithGoogle;
use Sunrice\Account\Events\Anonymized;
use Sunrice\Account\Facades\Account;
use Sunrice\Account\Notifications\ResetPasswordNotification;
use Sunrice\Account\Profiles\Actions\AnonymizeAccount;
use Sunrice\Account\Profiles\Actions\RequestDeletion;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Support\Card;
use Workbench\App\Models\User;

function accountsAdmin(): User
{
    $user = User::query()->create(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'secret-pass', 'email_verified_at' => now()]);
    $user->assignRole(Role::findOrCreate((string) config('sunrice-admin.super_admin_role', 'super-admin'), 'web'));

    return $user;
}

/** Staff who open the admin with some permissions, and nothing else. */
function panelStaff(array $permissions, string $email = 'staff@example.com'): User
{
    $role = Role::findOrCreate('role-'.md5($email), 'web');
    $role->syncPermissions(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), [Panel::ACCESS, ...$permissions]));
    $user = User::query()->create(['name' => 'Staff', 'email' => $email, 'password' => 'secret-pass', 'email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

/** A site account: registered (it has a profile) with the member role. */
function memberAccount(array $attributes = [], array $profile = []): User
{
    $user = siteUser($attributes);
    $user->assignRole(Role::findOrCreate('member', 'web'));
    Profile::forUser($user)->forceFill($profile)->save();

    return $user;
}

/** The addresses the Accounts list shows to this staff member. */
function listedAccounts(User $staff, string $query = ''): array
{
    $emails = [];
    test()->actingAs($staff, 'web')->get('/admin/account/accounts'.$query)->assertOk()
        ->assertInertia(function ($page) use (&$emails) {
            $emails = array_column(array_column($page->toArray()['props']['rows']['data'], 'cells'), 'email');

            return $page->component('Resources/Index');
        });
    sort($emails);

    return $emails;
}

it('tells staff from accounts by whether they open the admin', function () {
    $boss = accountsAdmin();
    $staff = panelStaff([]);
    $member = memberAccount();
    $direct = siteUser(['email' => 'direct@example.com']);
    $direct->givePermissionTo(Permission::findOrCreate(Panel::ACCESS, 'web'));
    $fresh = siteUser(['email' => 'fresh@example.com']); // made in Users, no role yet

    expect(Accounts::isStaff($boss))->toBeTrue()
        ->and(Accounts::isStaff($staff))->toBeTrue()
        ->and(Accounts::isStaff($direct))->toBeTrue()
        ->and(Accounts::isStaff($member))->toBeFalse()
        ->and(Accounts::accounts(User::query())->orderBy('id')->pluck('email')->all())->toBe(['ana@example.com', 'fresh@example.com'])
        ->and(Accounts::staff(User::query())->orderBy('id')->pluck('email')->all())->toBe(['boss@example.com', 'staff@example.com', 'direct@example.com'])
        // Users keeps someone without a role in sight until their roles are chosen, but never a registered account.
        ->and(AccountUserResource::query()->orderBy('id')->pluck('email')->all())->toBe(['boss@example.com', 'staff@example.com', 'direct@example.com', 'fresh@example.com']);
});

it('lists accounts in Administration → Accounts, behind its permissions', function () {
    accountsAdmin();
    memberAccount(['name' => 'Rina', 'email' => 'rina@example.com', 'email_verified_at' => null], ['phone' => '0812']);
    expect(app(Panel::class)->getPermissions())->toHaveKeys(['account.accounts.view', 'account.accounts.update', 'account.accounts.delete', 'account.accounts.export']);

    expect(listedAccounts(accountsAdminAgain()))->toBe(['rina@example.com'])
        ->and(listedAccounts(accountsAdminAgain(), '?filters[verified]=1'))->toBe([])
        ->and(listedAccounts(accountsAdminAgain(), '?filters[verified]=0&filters[role]=member&search=rina'))->toBe(['rina@example.com']);

    $this->actingAs(panelStaff([]), 'web')->get('/admin/account/accounts')->assertForbidden();
    $this->actingAs(panelStaff(['account.accounts.view'], 'viewer@example.com'), 'web')->get('/admin/account/accounts')->assertOk();
    // Accounts are made on the site.
    $this->actingAs(accountsAdminAgain(), 'web')->get('/admin/account/accounts/create')->assertForbidden();
});

function accountsAdminAgain(): User
{
    return User::query()->where('email', 'boss@example.com')->firstOrFail();
}

it('keeps accounts out of Users, and exports the list', function () {
    $boss = accountsAdmin();
    $member = memberAccount(['email' => 'rina@example.com']);

    $this->actingAs($boss, 'web')->get("/admin/users/{$member->id}/edit")->assertNotFound();
    $this->actingAs($boss, 'web')->get("/admin/users/{$boss->id}/edit")->assertOk();

    $csv = $this->actingAs($boss, 'web')->get('/admin/account/accounts/export')->assertOk()->streamedContent();
    expect($csv)->toContain('rina@example.com')->not->toContain('boss@example.com');
    $this->actingAs(panelStaff(['account.accounts.view']), 'web')->get('/admin/account/accounts/export')->assertForbidden();
});

it('removes the personal data of several accounts at once', function () {
    $boss = accountsAdmin();
    $a = memberAccount(['email' => 'a@example.com']);
    $b = memberAccount(['email' => 'b@example.com']);

    $this->actingAs($boss, 'web')->post('/admin/account/accounts/bulk', ['action' => 'delete', 'ids' => [$a->id, $b->id]])->assertSessionHasNoErrors();

    expect($a->fresh()->email)->toEndWith('@invalid.local')->and($b->fresh()->email)->toEndWith('@invalid.local');
});

it('never reaches staff from the Accounts screen', function () {
    $boss = accountsAdmin();
    $staff = panelStaff([]);

    $this->actingAs($boss, 'web')->get("/admin/account/accounts/{$staff->id}/edit")->assertNotFound();
    $this->actingAs($boss, 'web')->post("/admin/account/accounts/{$staff->id}/actions/delete")->assertNotFound();
    expect($staff->fresh()->email)->toBe('staff@example.com');
});

it('edits an account\'s name, address, phone and site roles', function () {
    $boss = accountsAdmin();
    Role::findOrCreate('premium', 'web');
    $editor = Role::findOrCreate('editor', 'web');
    $editor->givePermissionTo(Permission::findOrCreate(Panel::ACCESS, 'web'));
    $member = memberAccount();

    $this->actingAs($boss, 'web')->get("/admin/account/accounts/{$member->id}/edit")->assertOk()
        ->assertSee('premium')->assertDontSee('"editor"', false);

    // Only site roles are offered and taken: a role that opens the admin is never given here.
    $this->actingAs($boss, 'web')->put("/admin/account/accounts/{$member->id}", [
        'name' => 'Ana Putri', 'email' => 'ana.putri@example.com', 'roles' => ['premium', 'editor'],
    ])->assertSessionHasErrors();
    expect(Account::roles($member->fresh()))->toBe(['member']);

    $this->actingAs($boss, 'web')->put("/admin/account/accounts/{$member->id}", [
        'name' => 'Ana Putri', 'email' => 'ana.putri@example.com', 'phone' => ' 0812 3456 ', 'roles' => ['premium'],
    ])->assertSessionHasNoErrors();

    $member->refresh();
    expect([$member->name, $member->email])->toBe(['Ana Putri', 'ana.putri@example.com'])
        ->and(Profile::forUser($member)->phone)->toBe('0812 3456')
        ->and(Account::roles($member))->toBe(['premium'])
        ->and(Accounts::isStaff($member))->toBeFalse();

    $this->actingAs($boss, 'web')->put("/admin/account/accounts/{$member->id}", ['name' => 'Ana', 'email' => 'Ana@Example.com', 'roles' => []])
        ->assertSessionHasErrors('email');
});

it('emails a password link, marks an address verified and dismisses a deletion request', function () {
    Notification::fake();
    $boss = accountsAdmin();
    $member = memberAccount(['email_verified_at' => null]);
    app(RequestDeletion::class)->handle($member);
    $actions = "/admin/account/accounts/{$member->id}/actions";

    $this->actingAs($boss, 'web')->post("{$actions}/reset-link")->assertSessionHasNoErrors();
    Notification::assertSentTo($member, ResetPasswordNotification::class);

    $this->actingAs($boss, 'web')->post("{$actions}/verify")->assertSessionHasNoErrors();
    expect($member->fresh()->email_verified_at)->not->toBeNull();

    expect(listedAccounts($boss, '?filters[deletion]=1'))->toBe(['ana@example.com']);
    $this->actingAs($boss, 'web')->post("{$actions}/dismiss-deletion")->assertSessionHasNoErrors();
    expect(Profile::forUser($member)->deletion_requested_at)->toBeNull();
    expect(listedAccounts($boss, '?filters[deletion]=1'))->toBe([]);

    // Updating isn't enough to remove personal data.
    $this->actingAs(panelStaff(['account.accounts.view', 'account.accounts.update']), 'web')->post("{$actions}/delete")->assertForbidden();
    expect($member->fresh()->email)->toBe('ana@example.com');
});

it('removes an account\'s personal data and keeps the row', function () {
    $boss = accountsAdmin();
    $member = memberAccount(['remember_token' => 'remembered'], ['phone' => '0812', 'locale' => 'en', 'accepts_marketing' => true, 'marketing_consent_at' => now(), 'meta' => ['shop' => ['tax_id' => '01.234']]]);
    DB::table(SignInWithGoogle::TABLE)->insert(['user_id' => $member->id, 'provider' => 'google', 'provider_id' => 'g-1', 'email' => 'ana@example.com', 'created_at' => now(), 'updated_at' => now()]);
    app(RequestDeletion::class)->handle($member);
    $heard = null;
    Event::listen(Anonymized::class, function (Anonymized $event) use (&$heard): void {
        $heard = [$event->email, $event->user->getAuthIdentifier()];
    });

    $this->actingAs($boss, 'web')->post("/admin/account/accounts/{$member->id}/actions/delete")->assertSessionHasNoErrors();

    $member->refresh();
    $profile = Profile::forUser($member);
    expect($member->name)->toBe(AnonymizeAccount::NAME)
        ->and($member->email)->toStartWith("deleted-{$member->id}-")->toEndWith('@invalid.local')
        ->and($member->email_verified_at)->toBeNull()
        ->and($member->remember_token)->not->toBe('remembered')
        ->and(Hash::check('secret-pass', $member->password))->toBeFalse()
        ->and($member->roles()->count())->toBe(0)
        ->and([$profile->phone, $profile->locale, $profile->accepts_marketing, $profile->marketing_consent_at, $profile->meta])->toBe([null, null, false, null, null])
        ->and($profile->anonymized_at)->not->toBeNull()
        ->and(DB::table(SignInWithGoogle::TABLE)->where('user_id', $member->id)->exists())->toBeFalse()
        ->and($heard)->toBe(['ana@example.com', $member->id])
        // The log doesn't keep what was just removed.
        ->and(DB::table('sunrice_activity')->where('action', 'personal data removed')->where('subject_label', 'like', '%ana@example.com%')->exists())->toBeFalse();

    // Gone from the list, and from the site: the old address and password sign nobody in.
    expect(listedAccounts($boss))->toBe([]);
    $this->actingAs($boss, 'web')->get("/admin/account/accounts/{$member->id}/edit")->assertNotFound();
    auth('web')->logout();
    $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'secret-pass'])->assertSessionHasErrors();
    $this->assertGuest('account');

    // Once: a second time changes nothing.
    expect(app(AnonymizeAccount::class)->handle($member))->toBeFalse();
});

it('refuses to remove the personal data of staff', function () {
    $staff = panelStaff([]);

    expect(fn () => app(AnonymizeAccount::class)->handle($staff))->toThrow(DomainException::class)
        ->and($staff->fresh()->email)->toBe('staff@example.com');
});

it('shows what the profile holds, and plugins\' cards, beside an account', function () {
    $boss = accountsAdmin();
    $member = memberAccount([], ['locale' => 'en']);
    Account::adminCard(fn ($account, $viewer) => Card::make('Orders')->rows(['Orders' => 3, 'For' => $account->email]));
    Account::adminCard(fn () => null);

    $cards = array_map(fn (Card $card) => $card->toArray(), AccountResource::aside($member, $boss));

    expect(array_column($cards, 'title'))->toBe(['Account', 'Orders'])
        ->and(json_encode($cards))->toContain('Language')->toContain('ana@example.com');
    $this->actingAs($boss, 'web')->get("/admin/account/accounts/{$member->id}/edit")->assertOk();
});
