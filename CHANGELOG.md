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
