# Changelog

## Unreleased

- The package: `sunrice/account`, a Sunrice plugin (`AccountPlugin`, id `account`, after the CMS).
  - The site's own session guard, `account` (`sunrice-account.auth.guard`), defined on the user
    provider of the panel's guard unless `config/auth.php` defines it.
  - Account settings (`AccountSettings`, `Account::settings()`): one `account` row in the panel's
    settings table over `config('sunrice-account.defaults')`, saved a section at a time.
  - Profiles (`account_profiles`, `Account::profile($user)`): phone, language, marketing consent,
    deletion request and `meta` for plugins.
  - The `account.settings.manage` permission and the `member` role (no admin permissions), created
    by `sunrice:roles`.
- Account pages and their routes:
  - Under the account prefix (`sunrice-account.paths.prefix`, `account`; the settings page's
    `general.path` wins), in every language of the site: `/account`, `/en/account`. Route names
    `account.*`, with `.locale` for the other languages; `Account::url()` builds the address in the
    current language. `AccountRoutes::localized()` registers any routes that way.
  - `Account::routes()` adds other plugins' pages under the prefix, signed-in only unless said.
  - Middleware `account.auth` (guests go to `account.login` and come back), `account.guest` and
    `account.noindex` (`X-Robots-Tag`, `Cache-Control: private, no-store`).
  - Views `account::` with a layout lookup (`Layout::site()`, `Layout::view()`): the site layout
    (`sunrice-account.layout`, `cms.layout`) or the package's minimal one, and the app's copies in
    `resources/views/account/` before the package's (tag `sunrice-account-views`). Translations in
    Indonesian and English.
  - My Account's first page (`account.dashboard`).
  - The CMS's page cache never keeps account pages, and gives signed-in accounts fresh pages.
- Signing in, registering and resetting a password on the site:
  - Pages under the account prefix (`/account/login`, `register`, `forgot-password`,
    `reset-password/{token}`; paths in `sunrice-account.paths`), guests only, in every language;
    `POST /account/logout` signs out of the site only, keeping an admin session.
  - Throttled per email + IP and per IP (sign-in 5/30 a minute, registration 5 an hour per IP,
    reset links 3/10 per 10 minutes), the panel's captcha when it's set up, a honeypot on every
    form, and the panel's password policy for new passwords. Locked accounts can't sign in, and
    while the site shares the admin's guard, staff are sent to the admin's sign-in.
  - Nothing reveals whether an email has an account: a registration with a taken email creates
    nothing and emails its owner instead (`ExistingAccountNotification`); reset requests always
    get the same answer.
  - New accounts get the default roles (`general.default_roles`) and a profile (phone, language,
    marketing consent). Settings `general.phone` (off/optional/required), `marketing_consent`,
    `terms_url` and `redirect_after_login`.
  - Events: `Registering` (change the data, or refuse with a `ValidationException`), `Registered`,
    `SignedIn`, `SignedOut`, `PasswordReset`. Actions in `Sunrice\Account\Auth\Actions`.
- Email verification (`general.verify_email`, on by default):
  - A new account gets a signed link (`/account/verify/{id}/{hash}`, `account.verify`, valid for
    `auth.verification.expire` minutes, a day by default) instead of being signed in; the answer is
    the same as for a taken email. The link verifies the address, signs the account in and opens
    My Account in the language it was sent in. A changed email invalidates its old links.
  - Signing in with an unverified email is refused, with a new link at most once a minute;
    `/account/verify` (`account.verification.notice`) asks for one, with the same answer for any
    email.
  - Staff while the site shares the admin's guard, and locked accounts, are verified by their
    link but not signed in.
  - Middleware `account.verified` for pages that need a verified email; `Verification::verified()`,
    `Verification::url()`; `Verified` event; actions `SendVerificationLink` and `VerifyEmail`.
- Account emails (`Account::emails()`, `Account::emailSettings()`):
  - A registry of `EmailDefinition`s (key, label, audience, locked, default texts): the package's
    welcome, verify email, password reset, account already exists, and to staff, new account.
    Plugins register theirs.
  - Settings per email under `emails.{key}`: `enabled` (the verify, reset and existing-account
    emails can't be switched off), `subject` per language (`:site`, `{name}` placeholders), and for
    staff emails `addresses` and `roles` (default: super admins and administrators).
  - `AccountEmail`, the base of every account email: queued, in one language, with the site's mail
    brand (`Panel::mailBrand()`) in `account::mail.notification`; an app replaces an email's body
    with `resources/views/account/emails/{key}.blade.php`.
  - New: the welcome email (at registration while emails aren't verified, else once verified) and
    the staff email of a new account (to the chosen roles' users and addresses, each once, in the
    site's main language).
- Sign in with an emailed code (`general.login_code`, off by default), with
  `spatie/laravel-one-time-passwords`: the user model needs its `HasOneTimePasswords` trait, and its
  `one_time_passwords` migration (`php artisan vendor:publish --tag=one-time-passwords-migrations`).
  6 digits, valid for `general.code_minutes` (10), once, from the browser that asked, 5 tries a
  minute per account plus 10 per 10 minutes per email and IP; one code a minute per email; the same
  answer for any email. A code verifies the email. `LoginCodes`, email `account_login_code` (locked).
- Sign in with Google (`general.google_login`, off by default; `laravel/socialite`, credentials
  from `services.google` or `sunrice-account.google`): a linked account signs in; a verified
  Google email links the account that has it; otherwise an account is created (verified, default
  roles, `password_set` false) while registration is open. Links in `account_social_accounts`.
  Callback `/account/login/google/callback` (`account.google.callback`).
- Staff while the site shares the admin's guard, and locked accounts, can't sign in by code or
  Google. `SignedIn::$method` is `code` or `google` for them.
