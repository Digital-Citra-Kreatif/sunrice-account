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
