# Working on Sunrice Account

`sunrice/account` (namespace `Sunrice\Account\`, plugin id `account`) gives a Sunrice CMS site
accounts: registration, sign-in, password reset, email verification, a My Account area other
plugins extend, account emails, roles and an account settings page other plugins can take over.
It is the foundation of sunrice-commerce's customer accounts. `PLAN.md` is the source of truth for
scope, structure and order; read it before starting a task.

## Rules

- One task per branch and pull request, titled with what it does.
- Use only Sunrice's public API (classes and methods marked `@api` in `sunrice/admin` and
  `sunrice/cms`). Missing something? Propose it for Sunrice in the PR instead of reaching in.
- This package never uses `Sunrice\Commerce` or any other plugin built on it (`tests/Arch`).
  Plugins extend it through the `Account` API and its events.
- No feature detection (`method_exists`, reflection) between packages: state versions in
  `composer.json`.
- Security by default: every route and page declares what guards it; validate all input, escape
  all output; messages never reveal whether an email has an account.
- Every task ships Pest tests (Orchestra Testbench). Before opening a PR run `composer test`,
  `composer lint` and `composer analyse` (Larastan at `level: max`; no ignores without a comment).
- Until 1.0 there is no backwards compatibility to keep, but record every change in
  `CHANGELOG.md` and update `docs/` when adding developer-facing API.
- Do not commit `vendor/`, `node_modules/`, `composer.lock` or `.env`.

## Conventions

- PHP 8.4+, Laravel 13. Every PHP file starts `declare(strict_types=1);`.
- Config `config/sunrice-account.php` (key `sunrice-account`); views `account::`; translations
  `account::`; tables prefixed `account_`.
- Permissions `account.<area>.<action>`; admin route names `sunrice.admin.account.<area>.<action>`;
  site route names `sunrice.account.<name>` (+ `.locale` for the other languages); middleware
  aliases `sunrice.account.*`; views overridden in `resources/views/vendor/account`.
- Write operations live in single-purpose action classes `src/<Area>/Actions/` with a public
  `handle()`. Controllers stay thin.
- Settings live in `AccountSettings` (one `account` row in the panel's settings table over
  `config('sunrice-account.defaults')`).

## Commands

```bash
composer test      # Pest
composer lint      # Pint (test mode)
composer format    # Pint (fix)
composer analyse   # Larastan
vendor/bin/testbench serve   # the workbench site
```
