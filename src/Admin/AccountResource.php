<?php

declare(strict_types=1);

namespace Sunrice\Account\Admin;

use DomainException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Sunrice\Account\Account;
use Sunrice\Account\Auth\Actions\SendPasswordResetLink;
use Sunrice\Account\Auth\Actions\SignInWithGoogle;
use Sunrice\Account\Auth\Actions\VerifyEmail;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Profiles\Actions\AnonymizeAccount;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\CheckboxList;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Resources\Tables\Table;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Admin\Security\SecurityLog;
use Sunrice\Admin\Support\Card;
use Sunrice\Admin\Users\PermissionRank;
use Sunrice\Cms\Support\Locales;

/**
 * Administration → Accounts: the site's accounts (users who don't open the
 * admin, see Accounts), behind `account.accounts.view`, `.update`,
 * `.delete` and `.export`. Staff change a name, address, phone and roles,
 * email a password link, mark an address verified, end sessions, and
 * answer deletion requests by removing the account's personal data.
 *
 * Accounts are made on the site (or as a user, in Users): there is no
 * "New account". Nothing here deletes a user row, since orders, comments
 * and logs point at it: Delete removes the personal data instead
 * (AnonymizeAccount), after which the account leaves the list.
 *
 * Plugins add cards beside an account's form with `Account::adminCard()`.
 *
 * @api
 */
class AccountResource extends Resource
{
    public static function model(): string
    {
        return Users::model();
    }

    public static function key(): string
    {
        return 'accounts';
    }

    public static function label(): string
    {
        return 'Accounts';
    }

    public static function singularLabel(): string
    {
        return 'Account';
    }

    public static function navigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function navigationIcon(): string
    {
        return 'contact';
    }

    public static function navigationSort(): int
    {
        return 901;
    }

    public static function query(): Builder
    {
        $query = parent::query();
        $key = $query->getModel()->getQualifiedKeyName();

        // The profile's columns the list shows, read with the page (the user model has no relation to it).
        return Accounts::accounts($query)->with('roles')->addSelect([
            'account_phone' => Profile::query()->select('phone')->whereColumn('user_id', $key)->limit(1),
            'account_deletion_requested_at' => Profile::query()->select('deletion_requested_at')->whereColumn('user_id', $key)->limit(1),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Account')->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required()->unique()->rules(['lowercase'])
                    ->help('In lower case. Changing it here doesn\'t ask the account to verify the new address.'),
                TextInput::make('phone')->maxLength(32)
                    ->loadUsing(fn (Model $user): ?string => static::profile($user)->phone)
                    ->saveUsing(function (Model $user, mixed $phone): void {
                        static::profile($user)->forceFill(['phone' => is_string($phone) && trim($phone) !== '' ? trim($phone) : null])->save();
                    }),
            ]),
            Section::make('Roles')->description('What sets this account apart on the site. None of these roles opens the admin.')->schema([
                static::rolesField(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('name')->searchable()->sortable(),
                Column::make('email')->searchable()->sortable(),
                Column::make('roles.name')->label('Roles')->badge()
                    ->state(fn (Model $user): string => implode(', ', static::roleNames($user))),
                Column::make('verified')->label('Email verified')->boolean()
                    ->state(fn (Model $user): bool => $user->getAttribute('email_verified_at') !== null),
                Column::make('deletion')->label('Asked to be deleted')->boolean()
                    ->state(fn (Model $user): bool => $user->getAttribute('account_deletion_requested_at') !== null),
                Column::make('created_at')->label('Created')->date()->sortable(),
            ])
            ->filters([
                Filter::make('role')->options(fn (): array => static::siteRoles())
                    ->query(fn (Builder $query, string $role) => $query->whereRelation('roles', 'name', $role)),
                Filter::make('verified')->label('Email verified')->boolean()
                    ->query(fn (Builder $query, string $value) => $value === '1' ? $query->whereNotNull('email_verified_at') : $query->whereNull('email_verified_at')),
                Filter::make('deletion')->label('Asked to be deleted')->options(['1' => 'Yes'])
                    ->query(fn (Builder $query) => $query->whereIn($query->getModel()->getQualifiedKeyName(), Profile::query()->select('user_id')->whereNotNull('deletion_requested_at'))),
            ])
            ->actions([
                Action::make('reset-link')->label('Email a password link')
                    ->confirm('Email this account a link to choose a new password? Their current password keeps working until they do.')
                    ->handle(function (Model $user): string {
                        $email = $user->getAttribute('email');
                        app(SendPasswordResetLink::class)->handle(is_string($email) ? $email : '', static::localeOf($user));

                        return static::say('A password link is on its way to :name.', ['name' => static::title($user)]);
                    }),
                Action::make('verify')->label('Mark email as verified')
                    ->confirm('Mark this address as verified? Only when you know it is theirs: the account can then sign in without following the link.')
                    ->visible(fn (Model $user): bool => $user->getAttribute('email_verified_at') === null)
                    ->handle(function (Model $user): string {
                        if ($user instanceof Authenticatable) {
                            app(VerifyEmail::class)->handle($user, static::localeOf($user));
                            app(SecurityLog::class)->record('email marked verified', $user);
                        }

                        return static::say('Email marked as verified.');
                    }),
                Action::make('sign-out')->label('Sign out everywhere')
                    ->confirm('End all of this account\'s sessions? They can sign in again.')
                    ->handle(function (Model $user): string {
                        app(AccountGuard::class)->signOutEverywhere($user);

                        return static::say('Signed out everywhere.');
                    }),
                Action::make('dismiss-deletion')->label('Dismiss the deletion request')
                    ->confirm('Dismiss this account\'s request to be deleted? Its data stays, and it can ask again.')
                    ->visible(fn (Model $user): bool => $user->getAttribute('account_deletion_requested_at') !== null)
                    ->handle(function (Model $user): string {
                        static::profile($user)->forceFill(['deletion_requested_at' => null])->save();
                        app(SecurityLog::class)->record('deletion request dismissed', $user);

                        return static::say('Deletion request dismissed.');
                    }),
                Action::make('delete')->label('Remove personal data…')->ability('delete')->destructive()
                    ->confirm('Remove this account\'s personal data? Its name, email, phone, roles and sign-ins go, and it can no longer sign in. What it did on the site stays, without the person. This can\'t be undone.')
                    ->handle(function (Model $user): string {
                        static::deleteRecord($user);

                        return static::say('Personal data removed.');
                    }),
                Action::bulk('delete')->label('Remove personal data…')->ability('delete')->destructive()
                    ->confirm('Remove the selected accounts\' personal data? Their names, emails, phones, roles and sign-ins go, and they can no longer sign in. This can\'t be undone.')
                    ->handle(function (SupportCollection $users): string {
                        DB::transaction(fn () => $users->each(fn (Model $user) => static::deleteRecord($user)));

                        return $users->count() === 1 ? static::say('Personal data removed.') : static::say('Personal data of :count accounts removed.', ['count' => $users->count()]);
                    }),
            ])
            ->defaultSort('-created_at')
            ->exportable();
    }

    /** The Account card (what the profile holds), then the cards plugins add. */
    public static function aside(Model $record, Authorizable $user): array
    {
        $profile = static::profile($record);
        $verified = $record->getAttribute('email_verified_at');
        $card = Card::make('Account')->description('What this account told the site. Marketing consent is theirs to give, on their profile page.');
        if ($profile->deletion_requested_at !== null) {
            $card->notice('Asked to be deleted', Card::WARNING, 'Remove their personal data from the list\'s menu, or dismiss the request.');
        }
        $card->rows([
            'Email verified' => $verified instanceof \DateTimeInterface ? $verified : static::say('No'),
            'Deletion asked' => $profile->deletion_requested_at,
            'Language' => $profile->locale,
            'Marketing emails' => $profile->accepts_marketing ? ($profile->marketing_consent_at ?? static::say('Yes')) : static::say('No'),
            'Password' => $profile->password_set ? static::say('Set') : static::say('Not set: signs in with a code or Google'),
            'Google' => DB::table(SignInWithGoogle::TABLE)->where('user_id', $record->getKey())->exists() ? static::say('Linked') : null,
        ]);

        return [$card, ...app(Account::class)->adminCards($record, $user)];
    }

    /** Never deleted: the personal data goes, the row stays for what points at it. */
    public static function deleteRecord(Model $record): void
    {
        if (! $record instanceof Authenticatable) {
            return;
        }
        try {
            app(AnonymizeAccount::class)->handle($record);
        } catch (DomainException $e) {
            throw new ActionFailed(static::say($e->getMessage()));
        }
    }

    public static function can(Authorizable $user, string $ability, ?Model $record = null): bool
    {
        // Accounts register on the site; staff are created in Users.
        return $ability !== 'create';
    }

    /** The roles checkboxes: site roles (none opens the admin) the signed-in user may give out. */
    protected static function rolesField(): CheckboxList
    {
        return CheckboxList::make('roles')
            ->options(function (): array {
                $actor = auth(app(Panel::class)->getGuard())->user();
                $rank = app(PermissionRank::class);

                return static::roles()
                    ->filter(fn (Role $role): bool => $actor instanceof Model && $rank->canManageRole($actor, $role))
                    ->mapWithKeys(fn (Role $role): array => [$role->name => $role->name])
                    ->all();
            })
            ->loadUsing(fn (Model $user): array => static::roleNames($user))
            ->saveUsing(function (Model $user, mixed $chosen): void {
                // Roles that aren't offered (another guard's, a staff role, ones out of the editor's reach) stay as they are.
                $offered = static::rolesField()->values();
                $chosen = array_values(array_filter(is_array($chosen) ? $chosen : [], 'is_string'));
                $before = static::roleNames($user);
                $after = [...array_values(array_diff($before, $offered)), ...array_values(array_intersect($offered, $chosen))];
                sort($after);
                if ($after === $before || ! method_exists($user, 'syncRoles')) {
                    return;
                }
                $user->syncRoles($after);
                app(SecurityLog::class)->record('roles changed', $user, null, [
                    'note' => implode('; ', array_filter([
                        ($added = array_values(array_diff($after, $before))) === [] ? null : 'added: '.implode(', ', $added),
                        ($removed = array_values(array_diff($before, $after))) === [] ? null : 'removed: '.implode(', ', $removed),
                    ])),
                    'added' => $added, 'removed' => $removed,
                ]);
            })
            ->help('Roles that open the admin are given in Users.');
    }

    /**
     * The panel guard's roles that don't open the admin, with their permissions.
     *
     * @return Collection<int, Role>
     */
    protected static function roles(): Collection
    {
        return Role::query()->where('guard_name', app(Panel::class)->getGuard())
            ->whereNotIn('name', array_keys(AccountSettings::staffRoles()))
            ->with('permissions')->orderBy('name')->get();
    }

    /** @return array<string, string> the site roles, for the list's filter */
    protected static function siteRoles(): array
    {
        return static::roles()->mapWithKeys(fn (Role $role): array => [$role->name => $role->name])->all();
    }

    /** @return list<string> the account's roles on the panel's guard, sorted */
    protected static function roleNames(Model $user): array
    {
        return $user instanceof Authenticatable ? app(Account::class)->roles($user) : [];
    }

    protected static function profile(Model $user): Profile
    {
        if (! $user instanceof Authenticatable) {
            throw new DomainException('The user model isn\'t Authenticatable.');
        }

        return Profile::forUser($user);
    }

    /** The language the account reads its emails in: its own, else the site's main one. */
    protected static function localeOf(Model $user): string
    {
        $locale = static::profile($user)->locale;

        return is_string($locale) && Locales::has($locale) ? $locale : Locales::default();
    }

    /** @param  array<string, string|int>  $replace */
    protected static function say(string $text, array $replace = []): string
    {
        $said = __($text, $replace);

        return is_string($said) ? $said : $text;
    }
}
