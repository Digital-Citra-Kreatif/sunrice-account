# Accounts

`sunrice/account` gives the site accounts of its own, kept apart from the admin: people register
and sign in on the site, and a site sign-in never opens the admin.

## The guard

Accounts sign in on the `account` session guard (`sunrice-account.auth.guard`). Unless
`config/auth.php` defines that guard, it is added on the user provider of the panel's guard
(`$panel->guard()`, `web` by default): accounts and staff are rows of the same users table, with
separate sessions. Members have no admin permissions, and the admin requires `sunrice.access`, so
they never get in.

Setting `sunrice-account.auth.guard` to the panel's guard shares one session between the site and
the admin. `SiteGuard::sharesPanelGuard()` tells you when that's the case.

```php
use Sunrice\Account\Facades\Account;

Account::guard();          // "account"
Account::user();           // the account signed in on the site, or null
Account::profile($user);   // its Profile (created on first need)
```

## Settings

**Settings → Accounts** (`/admin/settings/accounts`, permission `account.settings.accounts`) is a
panel settings section, `Sunrice\Account\Settings\AccountSettings`, in four parts: General,
Sign-in, Announcement and Emails. AI agents read and change it with the panel's settings tools.
Read it anywhere with `Account::settings()`; your code (a seeder) saves it like any settings:

```php
Account::settings()->registration;           // true
Account::settings()->roles();                // ['member']
Account::settings()->fill(['verifyEmail' => false])->save();
```

| Setting | Default | |
| --- | --- | --- |
| `registration` | `true` | Visitors can create an account |
| `verifyEmail` | `true` | New accounts confirm their email before signing in |
| `defaultRoles` | `['member']` | Roles new accounts get: only roles without permissions, never super admins or administrators |
| `path` | `''` | The account address prefix, over `paths.prefix`. Saving a new one clears the route cache |
| `phone` | `'optional'` | The phone number at registration: `off`, `optional` or `required` |
| `marketingConsent` | `true` | Offer the "news and offers" checkbox |
| `termsUrl` | `''` | A terms page to agree to at registration (a site path or a full URL) |
| `redirectAfterLogin` | `''` | A site path to go to after signing in (else My Account) |
| `deletionRequests` | `true` | Accounts can ask to be deleted from their profile page |
| `loginCode` | `false` | Offer sign-in with an emailed code |
| `codeMinutes` | `10` | How long a sign-in code works (1–60 minutes) |
| `googleLogin` | `false` | Offer sign-in with Google (refused until the credentials are set) |
| `announcement`, `announcementPosition` | off, `above` | An announcement in My Account: `tab`, `above` or `below` |
| `announcementTitle`, `announcementMessage` | `''` | Its title and message in the main language |
| `announcementTitles`, `announcementMessages` | `''` | In the other languages, as "code: text" lines |
| `welcomeEmail`, `welcomeSubject`… | on, `''` | The emails: see [Emails](#emails) |

Texts in several languages are kept as "code: text" lines (the panel's `Pairs` field):
`AccountSettings::line($settings->welcomeSubject, 'en')` gives one language's.

### Showing the settings on your plugin's page

A plugin with a settings page of its own (commerce's Settings → Store) can show the account
settings there, so there are never two places for them. In its `register()`:

```php
Account::settingsShownBy('shop');   // Settings → Accounts leaves the menu (its page is not found)
```

Then draw the form on your page with the panel's `SettingsFields`, and save it with `SaveSettings`,
checked exactly as Settings → Accounts checks it:

```php
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Settings\SettingsFields;

$fields = SettingsFields::make(AccountSettings::class, AccountSettings::form(new Form));
// edit(): ['accounts' => $fields->props()], drawn with FormSections from @imajiner/admin-sdk/ui
// update():
app(SaveSettings::class)->handle(app(AccountSettings::class), $fields->validate($request->input('accounts', [])));
```

`AccountSettings::sections()` gives the form's parts by key (`general`, `sign-in`,
`announcement`, `emails`) to show them in different places. Give the fields to AI agents with
`Panel::settingsPage(..., fields: fn () => $fields)`, and guard your page with your own permission.

## Profiles

`account_profiles` holds what an account has beside the users table: `phone`, `locale`,
`accepts_marketing` and `marketing_consent_at`, `password_set` (false for accounts made without a
password until they set one), `deletion_requested_at`, and `meta`, where plugins keep what they
add, under their own key:

```php
$profile = Account::profile($user);
$profile->metaValue('shop.tax_id');
```

## Roles and permissions

| | |
| --- | --- |
| `member` role | Given to new accounts. No admin permissions. Created by `php artisan sunrice:roles` |
| `account.settings.accounts` | Edit Settings → Accounts (a privileged permission) |

`Account::roles($user)` gives the names of an account's roles (on the panel's guard), sorted: what a
plugin checks to let only some accounts in.

## Pages and routes

Account pages live under the account prefix: `/account` (`sunrice-account.paths.prefix`, or Settings
→ Accounts' `path`), in every language of the site, like the CMS's pages: `/account`
in the language at the plain addresses, `/en/account` in English. Route names are `sunrice.account.*`
(prefixed, so they never take over an app's own `account.*` routes),
with `.locale` for the other languages, so build addresses with `Account::url()`:

```php
Account::url('sunrice.account.dashboard');                 // in the current language
Account::url('sunrice.account.dashboard', locale: 'en');   // http://example.test/en/account
```

Add your plugin's pages under the prefix with `Account::routes()`, from your service provider or
plugin. They're for signed-in accounts unless you pass `signedIn: false`, they're `noindex`, and
the page cache never keeps them:

```php
use Illuminate\Support\Facades\Route;
use Sunrice\Account\Facades\Account;

Account::routes(function (string $suffix) {
    Route::get('orders', OrdersController::class)->name('shop.account.orders'.$suffix);
});
```

Routes of your own elsewhere can use `AccountRoutes::localized(fn (string $suffix) => ...)` for the
same languages, and the middleware:

| Middleware | |
| --- | --- |
| `sunrice.account.auth` | Signed-in accounts only. Guests go to the sign-in page (`sunrice.account.login`) and come back |
| `sunrice.account.guest` | Guests only (sign in, register); signed-in accounts go to My Account |
| `sunrice.account.noindex` | `X-Robots-Tag: noindex`, `Cache-Control: private, no-store`, and the layout's robots tag |
| `sunrice.account.verified` | After `sunrice.account.auth`: a verified email while verification is required; others are sent a link and the verify page, which says where it went and can send it again |

To send a guest to sign in from a page of your own (a members-only page), return
`Account::redirectToSignIn()`: once signed in they come back to the page they asked for (or to the
`$returnTo` you pass: a path like `/checkout` or a URL of this site; anything else is ignored, so a
visitor's `?return=` can't send them to another site).

A visitor signed in to an account always gets fresh pages from the CMS's page cache.

## Views and layout

Account pages extend the site's layout: `sunrice-account.layout` (by default the CMS's
`cms.layout`, `resources/views/cms/layout.blade.php`), or the package's minimal one when the site
has none, which styles them (`account::partials.styles`: plain CSS for the `account-*` classes,
light and dark; a site's own layout styles them itself). Like it, they fill `@section('content')` and pass `$seoTitle`.

The signed-in area's own layout is `account::layout`; your pages extend it and fill
`@section('account')`:

```blade
@extends(\Sunrice\Account\View\Layout::view('layout'))

@section('account')
    <h1>Orders</h1>
@endsection
```

The pages push the package's CSS (plain, light and dark, for the `account-*` classes) to the
layout's `head` stack, once: give your layout `@stack('head')`. A theme that styles the account
pages itself turns it off with `sunrice-account.styles` (`SUNRICE_ACCOUNT_STYLES=false`); your own
pages under the account prefix can `@include(Layout::view('partials.push-styles'))` to get it.

To change an account view, publish the views (`php artisan vendor:publish
--tag=sunrice-account-views`) and edit your copy in `resources/views/vendor/account/`: an app's
`resources/views/vendor/account/dashboard.blade.php` is used before the package's `account::dashboard`
(Laravel's usual place for a package's views, so an app's own `resources/views/account/` is left alone).

## Signing in and registering

| Page | Route | |
| --- | --- | --- |
| `/account/login` | `sunrice.account.login` | Email and password; "keep me signed in" |
| `/account/register` | `sunrice.account.register` | While `registration` is on |
| `/account/forgot-password` | `sunrice.account.password.request` | Emails a reset link |
| `/account/reset-password/{token}` | `sunrice.account.password.reset` | Choose a new password |
| `POST /account/logout` | `sunrice.account.logout` | Signs out of the site (with a shared guard, of the admin too) |

The paths under the prefix come from `sunrice-account.paths`. These pages are for guests: a
signed-in account goes to My Account. After signing in, people go back to the page they were
going to, else `redirectAfterLogin`, else My Account.

What protects them:

- Rate limits per email + IP and per IP: 5 and 30 sign-in attempts a minute, 5 registrations an
  hour from one address, 3 and 10 reset links per 10 minutes.
- The panel's captcha (`sunrice-admin.captcha`) when it's set up, and a honeypot on the forms that send email (not on password sign-in, which password managers send at once; it is throttled).
- New passwords follow the panel's password policy (Settings → Security).
- Locked accounts (`AccountGuard::lock()`) can't sign in. While the site shares the admin's guard,
  accounts that open the admin are sent to the admin's sign-in, which has its own protections.
- Nothing tells a visitor whether an email has an account: every sign-in refusal reads the same,
  reset requests always get the same answer, and a registration with an email that has an account
  creates nothing and emails its owner instead.

New accounts get the default roles (`defaultRoles`) and a profile with their phone,
language and marketing consent.

### Sign in with a code or with Google

**Emailed code** (`loginCode`): the sign-in page offers "Email me a sign-in code". It
uses [spatie/laravel-one-time-passwords](https://github.com/spatie/laravel-one-time-passwords), so
before turning it on:

- add its trait to your user model:

  ```php
  use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;

  class User extends Authenticatable
  {
      use HasOneTimePasswords, HasRoles, Notifiable;
  }
  ```

- publish and run its migration: `php artisan vendor:publish --tag=one-time-passwords-migrations`,
  then `php artisan migrate`.

The code has 6 digits, works for `codeMinutes` minutes, once, and only in the browser
that asked for it; 5 tries a minute per account, and 10 per 10 minutes per email and IP. Asking
again replaces it (at most once a minute). Any email gets the same answer. A code proves the email,
so it verifies an unverified one. Codes go out as the `account_login_code` account email, not the
package's own notification, and sign in on the account guard only.

**Google** (`googleLogin`): set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` (or
`services.google`) and give Google the callback URL `https://your-site/account/login/google/callback`.

- An account linked to the Google user signs in (even if its email changed since).
- Else, a Google-verified email links the account that has it.
- Else, while registration is open, a new account is created: verified, with the default roles,
  welcomed, and staff are told.

Links are kept in `account_social_accounts`. Staff while the site shares the admin's guard, and
locked accounts, can't sign in either way.

### Email verification

While `verifyEmail` is on (the default), a new account isn't signed in: it gets an email
with a signed link (`sunrice.account.verify`, valid for `auth.verification.expire` minutes, a day by
default), and the visitor sees "check your email", as for an email that already has an account.
The link verifies the address, signs the account in and opens My Account in the language it was
sent in. Signing in with an unverified email is refused and sends a new link (at most once a
minute); `/account/verify` (`sunrice.account.verification.notice`) asks for one: a guest enters their email, a
signed-in account still to verify sees where the link went and can send it again. An expired or
used link leads there too, saying so.

An email is verified when the user's `email_verified_at` is set: by its link, by a password reset
(the reset link proves the address too), or by staff. Changing the email invalidates the old links.

```php
use Sunrice\Account\Auth\Verification;

Verification::verified($user);
Verification::url($user, 'en');   // a fresh signed link
```

Staff while the site shares the admin's guard, and locked accounts, are verified by their link but
not signed in by it.

### Keeping accounts safe

- **Taking back an unverified account.** Anyone can register with an address that isn't theirs; the
  account stays unverified. When the address's owner later signs in with an emailed code or with
  Google, which prove the address, that account's password is cleared and its other sessions end
  before it's verified, so whoever made it keeps no way in. The owner can then choose a password
  on the profile page.
- **The verification link** signs in only the first time, and never over someone already signed
  in: opened again, or by someone else, it verifies and sends them to sign in.
- **Ending sessions.** A locked account (the panel's security page) is signed out of the site on
  its next account page, and so is every session that signed in before "Sign out everywhere".
  Changing or resetting the password does that to the account's other sessions (`sunrice.account.auth`
  checks; site sign-ins note when they started, like the admin's).
- **Whether an email has an account** isn't told by sign-in (a wrong password and an unknown email
  take as long), the code check (every failure reads the same) or the forms that email. Changing
  the profile's address to one that's taken says so only after the current password is right, at
  most 5 wrong tries in 10 minutes. With verification off, registration still signs a new address
  in at once and answers "check your email" for a taken one.
- **Limits:** 20 wrong passwords an hour for one email from any IP, 5 sign-in codes an hour to one
  email, 5 wrong current passwords in 10 minutes on the profile page, besides the per-IP ones.
- Names and addresses visitors type are text in staff emails, never Markdown links.

### Events

| Event | When |
| --- | --- |
| `Registering` | Before an account is created: change `$event->data`, or refuse by throwing a `ValidationException` (its messages show on the form) |
| `Registered` | An account was created (`$user`, `$profile`, `$data`, `$locale`), after Laravel's `Registered` |
| `SignedIn` | An account signed in on the site (`$user`, `$method`: `password`, `registration`, `verification`; `$remember`) |
| `Verified` | An account verified its email with its link (`$user`, `$locale`) |
| `SignedOut` | An account signed out |
| `PasswordReset` | An account chose a new password with a reset link |

```php
Event::listen(Registering::class, function (Registering $event) {
    if (str_ends_with($event->data['email'], '@example.invalid')) {
        throw ValidationException::withMessages(['email' => 'Use a real address.']);
    }
});
```

## Emails

| Key (`Emails::…`) | To | | Can be off |
| --- | --- | --- | --- |
| `account_welcome` (`WELCOME`) | the account | once it's ready: at registration, or when its email is verified | yes |
| `account_verify` (`VERIFY`) | the account | its verification link | no |
| `account_password_reset` (`RESET`) | the account | a password reset link | no |
| `account_existing` (`EXISTING`) | the account | someone tried to register with its email | no |
| `account_staff_registration` (`STAFF_REGISTRATION`) | staff | someone created an account | yes |
| `account_staff_deletion` (`STAFF_DELETION`) | staff | an account asked to be deleted | yes |

Every email is queued and sent in one language: the account's emails in the language of the page
it came from, staff emails in the site's main language. They show the site's mail brand
(`Panel::mailBrand()`: Settings → Branding's logo or name).

Settings → Accounts → Emails sets them up (`AccountSettings::EMAILS` names each email's settings):

- `{email}Email` (`welcomeEmail`, `staffRegistrationEmail`, `staffDeletionEmail`): the switch;
  the security emails have none.
- `{email}Subject`: per language, as "code: subject" lines; `:site` (or `{site}`) and the email's
  own placeholders (`{name}`…) are replaced. Without one, the default text.
- Staff emails go to the users with `{email}Roles` (null: super admins and administrators) and
  to `{email}Addresses` (one per line), each once.

`Account::emailSettings()` reads them for any email (`enabled($key)`, `subject($key, $locale)`,
`addresses($key)`, `roles($key)`).

To change an email's body, add `resources/views/vendor/account/emails/{key}.blade.php`, a markdown mail
view (`<x-mail::message>`). It gets `$lang`, `$site` and the email's data (`$name`, `$url`…).

### Your plugin's emails

Register a definition, then build the notification on `AccountEmail`. It gets the switch, the
subject and the brand. Its settings are your plugin's to keep: give them with `settings:`
(without it, the email is on, with its default subject):

```php
use Sunrice\Account\Emails\AccountEmail;
use Sunrice\Account\Emails\EmailDefinition;

Account::emails()->register(new EmailDefinition('shop_order_note', 'Order note', 'shop::emails.note',
    settings: fn (): array => ['enabled' => app(ShopSettings::class)->orderNotes, 'subject' => app(ShopSettings::class)->orderNoteSubject],
));

final class OrderNote extends AccountEmail
{
    public static function key(): string { return 'shop_order_note'; }

    protected function build(MailMessage $mail, mixed $notifiable): MailMessage
    {
        return $mail->line($this->text('intro'));   // shop::emails.note.intro, in $this->lang
    }
}
```

`Account::emails()->all()` lists every email, for a plugin that shows them in a settings page of
its own.

## My Account

### The menu

My Account's menu has the package's **Overview** (`sunrice.account.overview`, position 0) and **Profile**
(`sunrice.account.profile`, 90), and your plugin's pages:

```php
Account::routes(fn (string $suffix) => Route::get('orders', OrdersController::class)->name('shop.orders'.$suffix));
Account::page('orders', fn () => __('shop::account.orders'), 'shop.orders', position: 10);
Account::page('points', 'Points', 'shop.points', visible: fn () => Shop::loyaltyOn());
Account::removePage('overview');
```

A page shows while its route exists and its `visible` check (if any) says yes; the current page is
marked (`aria-current`). Your pages extend `account::layout` (see Views and layout), which draws
the menu, the messages and the announcement.

### Where /account opens, and the dashboard

`/account` opens where `Account::landingUsing()` says (return null for the default), else the
announcement while it's the first tab, else the dashboard (always at `/account/overview`):

```php
Account::landingUsing(fn ($user) => Account::url('shop.orders'));
```

The dashboard shows cards: views, given `$user` and what your closure returns.

```php
Account::dashboardCard('points', 'shop::account.points-card', fn ($user) => ['points' => Points::of($user)], position: 20);
```

### Profile

`/account/profile` saves the name, email, phone (`phone`), marketing consent
(`marketingConsent`) and your profile fields. A new email needs the current password (for
accounts that have one) and, while emails are verified, is verified again: the account stays signed
in, its email unverified until it clicks the link. The page also changes the password (or lets an
account made by Google choose one) and, while `deletionRequests` is on, asks for the
account to be deleted: `deletion_requested_at` is set on its profile, staff get the
`account_staff_deletion` email, and you handle it in the admin.

| Event | When |
| --- | --- |
| `ProfileUpdating` | Before the profile is saved: change `$event->data` (name, email, phone, accepts_marketing, fields), or refuse with a `ValidationException` |
| `ProfileUpdated` | Saved; `$changed` lists what changed (`name`, `email`, `phone`, `accepts_marketing`, `fields.{key}`) |
| `PasswordChanged` | The password was changed or first chosen |
| `DeletionRequested` | The account asked to be deleted |

### Your fields on the registration form and the profile page

```php
Account::registrationFields(fn () => [
    ['key' => 'shop_company', 'label' => fn () => __('shop::fields.company'), 'rules' => ['nullable', 'string', 'max:120']],
]);
Account::profileFields(fn () => [
    ['key' => 'shop_company', 'label' => 'Company', 'rules' => ['nullable', 'string', 'max:120']],
    ['key' => 'shop_size', 'label' => 'Size', 'type' => 'select', 'options' => ['s' => 'Small', 'l' => 'Large']],
    ['key' => 'shop_newsletter', 'label' => 'Weekly letter', 'type' => 'checkbox'],
]);
```

Types are `text` (default), `textarea`, `select` (its options are checked) and `checkbox` (stored as
true or false). Keys are lowercase with underscores; prefix them with your plugin's name. Values are
kept in the profile's `meta` under their key: `Account::profile($user)->metaValue('shop_company')`.
They're posted as `fields[{key}]` and their errors are under `fields.{key}`.

### Announcement

The `announcement` settings show a message in My Account: as its first page (`position` `tab`,
which `/account` then opens), or `above` or `below` every account page. `title` and `message` are
per language, falling back to the main language's; the message is plain text (escaped, line breaks
kept).
