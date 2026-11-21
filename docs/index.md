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

`Account::settings()` reads the account settings: what was saved in the admin over
`config('sunrice-account.defaults')`.

```php
Account::settings()->boolean('general.registration');
Account::settings()->strings('general.default_roles');   // ['member']
Account::settings()->section('general');
Account::settings()->saveSection('general', ['registration' => false]);
```

Plugins built on accounts keep their own sections in the same row, under their own key
(`saveSection('shop', [...])`). Saving a section only replaces the keys it's given.

| Key | Default | |
| --- | --- | --- |
| `general.registration` | `true` | Visitors can create an account |
| `general.verify_email` | `true` | New accounts confirm their email before signing in |
| `general.default_roles` | `['member']` | Roles new accounts get |
| `general.path` | `null` | The account address prefix, over `paths.prefix` |
| `general.phone` | `'optional'` | The phone number at registration: `off`, `optional` or `required` |
| `general.marketing_consent` | `true` | Offer the "news and offers" checkbox at registration |
| `general.terms_url` | `null` | A terms page to agree to at registration |
| `general.redirect_after_login` | `null` | A site path to go to after signing in (else My Account) |
| `general.login_code` | `false` | Offer sign-in with an emailed code |
| `general.code_minutes` | `10` | How long a sign-in code works (1–60 minutes) |
| `general.google_login` | `false` | Offer sign-in with Google (needs the credentials) |

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
| `account.settings.manage` | Edit the account settings (a privileged permission) |

## Pages and routes

Account pages live under the account prefix: `/account` (`sunrice-account.paths.prefix`, or the
settings page's `general.path`), in every language of the site, like the CMS's pages: `/account`
in the language at the plain addresses, `/en/account` in English. Route names are `account.*`,
with `.locale` for the other languages, so build addresses with `Account::url()`:

```php
Account::url('account.dashboard');                 // in the current language
Account::url('account.dashboard', locale: 'en');   // http://example.test/en/account
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
| `account.auth` | Signed-in accounts only. Guests go to the sign-in page (`account.login`) and come back |
| `account.guest` | Guests only (sign in, register); signed-in accounts go to My Account |
| `account.noindex` | `X-Robots-Tag: noindex`, `Cache-Control: private, no-store`, and the layout's robots tag |
| `account.verified` | After `account.auth`: a verified email while verification is required; others are asked to verify first |

A visitor signed in to an account always gets fresh pages from the CMS's page cache.

## Views and layout

Account pages extend the site's layout: `sunrice-account.layout` (by default the CMS's
`cms.layout`, `resources/views/cms/layout.blade.php`), or the package's minimal one when the site
has none. Like it, they fill `@section('content')` and pass `$seoTitle`.

The signed-in area's own layout is `account::layout`; your pages extend it and fill
`@section('account')`:

```blade
@extends(\Sunrice\Account\View\Layout::view('layout'))

@section('account')
    <h1>Orders</h1>
@endsection
```

To change an account view, publish the views (`php artisan vendor:publish
--tag=sunrice-account-views`) and edit your copy in `resources/views/account/`: an app's
`account.dashboard` is used before the package's `account::dashboard` (`Layout::view()`).

## Signing in and registering

| Page | Route | |
| --- | --- | --- |
| `/account/login` | `account.login` | Email and password; "keep me signed in" |
| `/account/register` | `account.register` | While `general.registration` is on |
| `/account/forgot-password` | `account.password.request` | Emails a reset link |
| `/account/reset-password/{token}` | `account.password.reset` | Choose a new password |
| `POST /account/logout` | `account.logout` | Signs out of the site only |

The paths under the prefix come from `sunrice-account.paths`. These pages are for guests: a
signed-in account goes to My Account. After signing in, people go back to the page they were
going to, else `general.redirect_after_login`, else My Account.

What protects them:

- Rate limits per email + IP and per IP: 5 and 30 sign-in attempts a minute, 5 registrations an
  hour from one address, 3 and 10 reset links per 10 minutes.
- The panel's captcha (`sunrice-admin.captcha`) when it's set up, and a honeypot on every form.
- New passwords follow the panel's password policy (Settings → Security).
- Locked accounts (`AccountGuard::lock()`) can't sign in. While the site shares the admin's guard,
  accounts that open the admin are sent to the admin's sign-in, which has its own protections.
- Nothing tells a visitor whether an email has an account: every sign-in refusal reads the same,
  reset requests always get the same answer, and a registration with an email that has an account
  creates nothing and emails its owner instead.

New accounts get the default roles (`general.default_roles`) and a profile with their phone,
language and marketing consent.

### Sign in with a code or with Google

**Emailed code** (`general.login_code`): the sign-in page offers "Email me a sign-in code". The
code has 6 digits, works for `general.code_minutes` minutes, once, and only in the browser that
asked for it, with 5 tries; asking again replaces it (at most once a minute). Any email gets the
same answer. A code proves the email, so it verifies an unverified one. Codes are kept hashed in
`account_login_codes`: the user model needs nothing extra.

**Google** (`general.google_login`): set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` (or
`services.google`) and give Google the callback URL `https://your-site/account/login/google/callback`.

- An account linked to the Google user signs in (even if its email changed since).
- Else, a Google-verified email links the account that has it.
- Else, while registration is open, a new account is created: verified, with the default roles,
  welcomed, and staff are told.

Links are kept in `account_social_accounts`. Staff while the site shares the admin's guard, and
locked accounts, can't sign in either way.

### Email verification

While `general.verify_email` is on (the default), a new account isn't signed in: it gets an email
with a signed link (`account.verify`, valid for `auth.verification.expire` minutes, a day by
default), and the visitor sees "check your email", as for an email that already has an account.
The link verifies the address, signs the account in and opens My Account in the language it was
sent in. Signing in with an unverified email is refused and sends a new link (at most once a
minute); `/account/verify` (`account.verification.notice`) asks for one.

An email is verified when the user's `email_verified_at` is set: by its link, by a password reset
(the reset link proves the address too), or by staff. Changing the email invalidates the old links.

```php
use Sunrice\Account\Auth\Verification;

Verification::verified($user);
Verification::url($user, 'en');   // a fresh signed link
```

Staff while the site shares the admin's guard, and locked accounts, are verified by their link but
not signed in by it.

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

Every email is queued and sent in one language: the account's emails in the language of the page
it came from, staff emails in the site's main language. They show the site's mail brand
(`Panel::mailBrand()`: Settings → Branding's logo or name).

Their settings live in the account settings, under `emails.{key}`:

```php
Account::settings()->saveSection('emails', [
    'account_welcome' => ['enabled' => true, 'subject' => ['en' => 'Welcome aboard, {name}']],
    'account_staff_registration' => ['addresses' => 'office@example.com', 'roles' => ['administrator']],
]);
```

- `enabled`: the switch (the security emails ignore it).
- `subject`: per language; `:site` (or `{site}`) and the email's own placeholders (`{name}`…) are
  replaced. Without one, the default text.
- Staff emails go to the users with `roles` (default: super admins and administrators) and to
  `addresses`, each once.

To change an email's body, add `resources/views/account/emails/{key}.blade.php`, a markdown mail
view (`<x-mail::message>`). It gets `$lang`, `$site` and the email's data (`$name`, `$url`…).

### Your plugin's emails

Register a definition, then build the notification on `AccountEmail`. It gets the switch, the
subject from the settings and the brand:

```php
use Sunrice\Account\Emails\AccountEmail;
use Sunrice\Account\Emails\EmailDefinition;

Account::emails()->register(new EmailDefinition('shop_order_note', 'Order note', 'shop::emails.note'));

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
