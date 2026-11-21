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
