<?php

declare(strict_types=1);

namespace Sunrice\Account\Settings;

use Closure;
use Illuminate\Support\Facades\Route as RouteFacade;
use Spatie\Permission\Models\Role;
use Sunrice\Account\AccountPlugin;
use Sunrice\Account\Auth\Google;
use Sunrice\Account\Emails\Emails;
use Sunrice\Account\Facades\Account;
use Sunrice\Account\MyAccount\Announcement;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\CheckboxList;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\ListInput;
use Sunrice\Admin\Resources\Forms\NumberInput;
use Sunrice\Admin\Resources\Forms\Pairs;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\Textarea;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Settings\Settings;
use Sunrice\Cms\Support\Locales;

/**
 * The account settings: Settings → Accounts (`/admin/settings/accounts`,
 * permission `account.settings.accounts`), read anywhere with
 * `app(AccountSettings::class)` or `Account::settings()`:
 *
 *     Account::settings()->registration;      // true
 *     Account::settings()->fill(['verifyEmail' => false])->save();
 *
 * A plugin that shows these settings on a page of its own (commerce's
 * Settings → Store) calls `Account::settingsShownBy('commerce')`: the
 * section leaves the Settings menu, and the plugin draws this form there
 * with `SettingsFields::make(AccountSettings::class, AccountSettings::form(new Form))`.
 *
 * Texts per language are kept as "code: text" lines (`Pairs::fixed()`):
 * read them with `AccountSettings::line()`.
 *
 * @api
 */
final class AccountSettings extends Settings
{
    public const KEY = 'accounts';

    /** The phone number at registration. */
    public const PHONE = ['off', 'optional', 'required'];

    /**
     * The package's emails' settings properties: email key => property
     * prefix ("welcome": welcomeEmail, welcomeSubject…). Locked emails have
     * no switch; staff emails have addresses and roles.
     */
    public const EMAILS = [
        Emails::WELCOME => 'welcome',
        Emails::VERIFY => 'verify',
        Emails::RESET => 'reset',
        Emails::EXISTING => 'existing',
        Emails::LOGIN_CODE => 'loginCode',
        Emails::STAFF_REGISTRATION => 'staffRegistration',
        Emails::STAFF_DELETION => 'staffDeletion',
    ];

    // General

    /** Visitors can create an account. */
    public bool $registration = true;

    /** New accounts confirm their email address before they can sign in. */
    public bool $verifyEmail = true;

    /** @var list<string> roles new accounts get (none of them may open the admin) */
    public array $defaultRoles = [AccountPlugin::MEMBER_ROLE];

    /** The account address prefix; empty: config('sunrice-account.paths.prefix'). */
    public string $path = '';

    /** The phone number at registration: off, optional or required. */
    public string $phone = 'optional';

    /** Offer a "send me news and offers" checkbox. */
    public bool $marketingConsent = true;

    /** A terms page to agree to at registration (a URL or a site path); empty: none. */
    public string $termsUrl = '';

    /** A site path to go to after signing in; empty: My Account. */
    public string $redirectAfterLogin = '';

    /** Accounts can ask to be deleted from their profile page. */
    public bool $deletionRequests = true;

    // Sign-in

    /** Sign in with a code sent by email, which also creates the account of a new address while visitors can register. */
    public bool $loginCode = true;

    /** How long a sign-in code works, in minutes (1–60). */
    public int $codeMinutes = 10;

    /** Sign in with Google (needs the credentials). */
    public bool $googleLogin = false;

    // Announcement

    public bool $announcement = false;

    /** tab (My Account's first page), above or below every page. */
    public string $announcementPosition = 'above';

    /** In the main language; other languages in "code: text" lines. */
    public string $announcementTitle = '';

    public string $announcementTitles = '';

    public string $announcementMessage = '';

    public string $announcementMessages = '';

    // Emails: subjects per language ("code: subject" lines; empty: the default text)

    public bool $welcomeEmail = true;

    public string $welcomeSubject = '';

    public string $verifySubject = '';

    public string $resetSubject = '';

    public string $existingSubject = '';

    public string $loginCodeSubject = '';

    public bool $staffRegistrationEmail = true;

    public string $staffRegistrationSubject = '';

    /** One address per line. */
    public string $staffRegistrationAddresses = '';

    /** @var list<string>|null null: super admins and administrators */
    public ?array $staffRegistrationRoles = null;

    public bool $staffDeletionEmail = true;

    public string $staffDeletionSubject = '';

    /** One address per line. */
    public string $staffDeletionAddresses = '';

    /** @var list<string>|null null: super admins and administrators */
    public ?array $staffDeletionRoles = null;

    public static function key(): string
    {
        return self::KEY;
    }

    public static function label(): string
    {
        return 'Accounts';
    }

    public static function description(): string
    {
        return 'Site accounts: who can register, how they sign in, My Account and the account emails.';
    }

    /** Hidden while a plugin shows these settings on a page of its own (`Account::settingsShownBy()`). */
    public static function available(): bool
    {
        return Account::settingsOwner() === null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema(array_values(self::sections()));
    }

    /**
     * The form's sections by key (general, sign-in, announcement, emails),
     * for a plugin that shows some of them in its own places.
     *
     * @return array<string, Section>
     */
    public static function sections(): array
    {
        return [
            'general' => self::general(),
            'sign-in' => self::signIn(),
            'announcement' => self::announcementSection(),
            'emails' => self::emails(),
        ];
    }

    /**
     * A language's text in "code: text" lines (a Pairs field's value), or
     * '' when it has none.
     */
    public static function line(string $lines, string $locale): string
    {
        foreach (preg_split('/\R/', $lines) ?: [] as $line) {
            [$code, $text] = array_pad(explode(':', $line, 2), 2, '');
            if (strcasecmp(trim($code), $locale) === 0 && trim($text) !== '') {
                return trim($text);
            }
        }

        return '';
    }

    /** @return list<string> the roles new accounts get (defaultRoles, without empty names) */
    public function roles(): array
    {
        return array_values(array_filter(array_map(trim(...), $this->defaultRoles), fn (string $role): bool => $role !== ''));
    }

    private static function general(): Section
    {
        return Section::make('General')->columns(2)->schema([
            Toggle::make('registration')->label('Visitors can create an account')->span(2),
            Toggle::make('verifyEmail')->label('New accounts confirm their email address')->span(2)
                ->help('They can sign in once they have opened the link emailed to them. Signing in with an emailed code or with Google proves the address too.'),
            CheckboxList::make('defaultRoles')->label('Roles of new accounts')->span(2)
                ->options(fn (): array => self::siteRoles())
                ->rules([self::rule(self::checkRoles(...))])
                ->help('Only roles without admin permissions: new accounts never open the admin.'),
            TextInput::make('path')->label('Account address')->maxLength(60)->placeholder(self::configuredPrefix())
                ->rules([self::rule(self::checkPath(...))])
                ->help(fn (): string => 'Where the account pages are now: '.self::addresses().'. Lowercase letters, numbers and dashes.')
                ->warnWhenChanged('Links to the old address stop working, and so does sign-in with Google until its callback address is changed in Google Cloud.'),
            Select::make('phone')->label('Phone number at registration')->required()
                ->options(['off' => 'Not asked', 'optional' => 'Optional', 'required' => 'Required']),
            TextInput::make('termsUrl')->label('Terms page')->maxLength(255)->placeholder('/terms')
                ->rules([self::rule(self::checkLink(...))])
                ->help('When set, new accounts agree to it to register. A site path or a full URL.'),
            TextInput::make('redirectAfterLogin')->label('After signing in, go to')->maxLength(255)->placeholder('/members')
                ->rules([self::rule(self::checkSitePath(...))])
                ->help('A path on the site. Empty: My Account. A page someone was trying to open wins.'),
            Toggle::make('marketingConsent')->label('Offer "Send me news and offers"')->span(2),
            Toggle::make('deletionRequests')->label('Accounts can ask to be deleted')->span(2)
                ->help('From their profile page. Staff are emailed; they delete the account.'),
        ]);
    }

    private static function signIn(): Section
    {
        return Section::make('Sign-in')->description('Besides the email address and password.')->columns(2)->schema([
            Toggle::make('loginCode')->label('Sign in with a code sent by email')->span(2),
            NumberInput::make('codeMinutes')->label('A code works for (minutes)')->integer()->min(1)->max(60)->required()->showWhen('loginCode'),
            Toggle::make('googleLogin')->label('Sign in with Google')->span(2)
                ->rules([self::rule(self::checkGoogle(...))])
                ->help(fn (): string => 'Needs GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET. Give Google the callback address '.url('/'.AccountRoutes::prefix().'/'.AccountRoutes::path('login').'/google/callback').' (it changes with the account address).'),
        ]);
    }

    private static function announcementSection(): Section
    {
        $others = self::otherLanguages();

        return Section::make('Announcement')->description('A message in My Account: a holiday, a change of terms.')->columns(2)->schema([
            Toggle::make('announcement')->label('Show an announcement')->span(2),
            Select::make('announcementPosition')->label('Where')->required()->showWhen('announcement')
                ->options(['tab' => 'As My Account\'s first page', 'above' => 'Above every account page', 'below' => 'Below every account page'])
                ->rules(['in:'.implode(',', Announcement::POSITIONS)]),
            TextInput::make('announcementTitle')->label('Title')->maxLength(150)->showWhen('announcement')
                ->placeholder((string) __('account::account.announcement.title')),
            Textarea::make('announcementMessage')->label('Message')->rows(3)->maxLength(2000)->span(2)->showWhen('announcement')
                ->rules(['required_if_accepted:announcement'])
                ->help('Plain text; line breaks are kept.'),
            ...($others === [] ? [] : [
                Pairs::make('announcementTitles')->label('Title in other languages')->fixed($others)->maxLength(2000)->span(2)->showWhen('announcement'),
                Pairs::make('announcementMessages')->label('Message in other languages')->fixed($others)->maxLength(5000)->span(2)->showWhen('announcement')
                    ->help('One line per language (line breaks are for the message above only). Empty: the message above.'),
            ]),
        ]);
    }

    private static function emails(): Section
    {
        $fields = [];
        foreach (Account::emails()->all() as $key => $email) {
            $prefix = self::EMAILS[$key] ?? null;
            if ($prefix === null) {
                continue; // a plugin's email: its settings are the plugin's
            }
            $label = $email->label;
            if (! $email->locked) {
                $fields[] = Toggle::make($prefix.'Email')->label('Send "'.$label.'"')->span(2)->help($email->description);
            }
            $fields[] = Pairs::make($prefix.'Subject')->label('"'.$label.'" subject')->fixed(Locales::names())->maxLength(2000)->span(2)
                ->help(($email->locked ? $email->description.' ' : '').'Empty: the default subject. :name and :site are filled in.');
            if ($email->audience === 'staff') {
                $fields[] = CheckboxList::make($prefix.'Roles')->label('"'.$label.'" goes to the staff roles')->span(2)
                    ->options(fn (): array => self::staffRoles())->default(self::defaultStaffRoles())
                    ->help('Roles that open the admin. Each person gets it once.');
                $fields[] = ListInput::make($prefix.'Addresses')->label('"'.$label.'" also goes to the addresses')->emails()->max(20)->span(2);
            }
        }

        return Section::make('Emails')->description('What the account emails say, and who gets the staff emails. Their texts are in the translations; an app can replace an email with resources/views/vendor/account/emails/{key}.blade.php.')
            ->columns(2)->schema($fields);
    }

    /** @return array<string, string> the site's languages after the main one */
    private static function otherLanguages(): array
    {
        return array_slice(Locales::names(), 1, null, true);
    }

    private static function configuredPrefix(): string
    {
        return AccountRoutes::path('prefix');
    }

    /** "/account (and /en/account…)": where the account pages are now, in each language. */
    private static function addresses(): string
    {
        $prefix = AccountRoutes::prefix();
        $others = array_keys(self::otherLanguages());

        return '/'.$prefix.($others === [] ? '' : ' (and '.Locales::prefix((string) $others[0]).'/'.$prefix.' in '.Locales::names()[$others[0]].')');
    }

    /** @return list<string> super admins and administrators */
    public static function defaultStaffRoles(): array
    {
        $superAdmin = config('sunrice-admin.super_admin_role', 'super-admin');

        return [is_string($superAdmin) ? $superAdmin : 'super-admin', 'administrator'];
    }

    /**
     * The roles staff emails may go to: those that open the admin (the
     * super admin's, and roles with the panel's access permission), never
     * customers' roles, which would email every account.
     *
     * @return array<string, string>
     */
    public static function staffRoles(): array
    {
        $superAdmin = self::defaultStaffRoles()[0];
        $names = Role::query()->where('guard_name', app(Panel::class)->getGuard())
            ->where(fn ($query) => $query->where('name', $superAdmin)->orWhereHas('permissions', fn ($permissions) => $permissions->where('name', Panel::ACCESS)))
            ->orderBy('name')->pluck('name')->all();
        $roles = [];
        foreach ($names as $name) {
            if (is_string($name)) {
                $roles[$name] = $name;
            }
        }

        return $roles;
    }

    /** @return array<string, string> roles that may be given to new accounts: without permissions */
    private static function siteRoles(): array
    {
        $roles = [];
        foreach (Role::query()->where('guard_name', app(Panel::class)->getGuard())->doesntHave('permissions')->orderBy('name')->pluck('name')->all() as $name) {
            if (is_string($name) && ! in_array($name, self::defaultStaffRoles(), true)) {
                $roles[$name] = $name;
            }
        }

        return $roles;
    }

    /**
     * @param  Closure(mixed): ?string  $check  the error, or null
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    private static function rule(Closure $check): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($check): void {
            $error = $check($value);
            if ($error !== null) {
                $fail($error);
            }
        };
    }

    /** New accounts must never get into the admin: no super admin, no role with a permission. */
    private static function checkRoles(mixed $value): ?string
    {
        $names = array_values(array_filter(is_array($value) ? $value : [], 'is_string'));
        if ($names === []) {
            return null;
        }
        if (array_intersect($names, self::defaultStaffRoles()) !== []) {
            return 'New accounts can\'t be super admins or administrators.';
        }
        $privileged = Role::query()->where('guard_name', app(Panel::class)->getGuard())->whereIn('name', $names)->has('permissions')->pluck('name')->all();

        return $privileged === [] ? null : 'These roles open the admin, so new accounts can\'t get them: '.implode(', ', array_filter($privileged, 'is_string')).'.';
    }

    private static function checkPath(mixed $value): ?string
    {
        $path = is_string($value) ? trim($value, '/ ') : '';
        if ($path === '') {
            return null;
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $path) !== 1) {
            return 'Use lowercase letters, numbers and dashes, like "my-account".';
        }
        $admin = trim(app(Panel::class)->getPath(), '/');
        if ($path === $admin || in_array($path, Locales::segments(), true)) {
            return 'That address is the admin\'s or a language\'s.';
        }
        // The app's own pages there would be hidden behind the account pages (or hide them).
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $uri = trim($route->uri(), '/');
            if (! str_starts_with((string) $route->getName(), 'sunrice.account.') && ($uri === $path || str_starts_with($uri, $path.'/'))) {
                return 'The site already has pages at /'.$path.'. Choose another address.';
            }
        }

        return null;
    }

    private static function checkLink(mixed $value): ?string
    {
        $link = is_string($value) ? trim($value) : '';
        if ($link === '' || self::checkSitePath($link) === null) {
            return null;
        }

        return preg_match('#^https?://[^\s/$.?\#].[^\s]*$#i', $link) === 1 ? null : 'Use a site path (/terms) or a full URL (https://…).';
    }

    /** A path on this site: no scheme, no host, nothing that leaves it ("//evil.test"). */
    private static function checkSitePath(mixed $value): ?string
    {
        $path = is_string($value) ? trim($value) : '';
        if ($path === '') {
            return null;
        }

        return preg_match('#^/?(?!/)[A-Za-z0-9\-._~/%]*(?:\?[A-Za-z0-9\-._~/%=&+]*)?$#', $path) === 1 && ! str_contains($path, '//') && ! str_contains($path, '\\')
            ? null : 'Use a path on the site, like /welcome.';
    }

    private static function checkGoogle(mixed $value): ?string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) && ! Google::configured()
            ? 'Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET first.' : null;
    }
}
