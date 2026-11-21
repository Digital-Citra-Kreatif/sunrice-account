# Sunrice Account: plan

`sunrice/account` gives a Sunrice CMS site accounts, and becomes the foundation of
sunrice-commerce's customer accounts. Commerce today has all of this built in
(`src/Customers`, `src/Http/Controllers/Account`, `routes/account.php`, the account emails, Settings
→ Store → Accounts). Phase 1 builds the generic part here. Phase 2 rewrites commerce on top of it.

## Decisions

| | |
| --- | --- |
| Package | `sunrice/account`, namespace `Sunrice\Account\`, plugin id `account`, requires `sunrice/admin` and `sunrice/cms` ^0.8 |
| Guard | `account` session guard, on the panel guard's user provider. Same users table, separate session |
| Default role | `member`, no admin permissions, seeded by `sunrice:roles`. Commerce adds `customer` |
| Sign-in methods | Password, emailed one-time code (spatie/laravel-one-time-passwords) and Google (laravel/socialite), the last two optional and off by default |
| Account URL | In config (`paths`), overridable in the settings page. Routes read it when they're registered; saving it clears the route cache |
| Admin | Administration → Accounts (list, roles, reset link, mark verified, deletion requests, anonymize). Commerce's Shop → Customers extends it |
| Plugin data | Plugins add pages, dashboard cards, profile and registration fields (stored in `account_profiles.meta`) and listen to events |
| Profile data | Phone, language and marketing consent live here, not in commerce |
| Settings page | One page, which a plugin can take over completely: commerce shows the account settings inside Settings → Store, and the Accounts page disappears, so there are never two |

## How it fits Sunrice

- A plugin can't replace another plugin's settings page in the panel (duplicate keys throw), so the
  override is built into this package: the page is drawn from a section registry, and a plugin
  that requires `account` (its `register()` runs before our `boot()`) can add, replace or remove
  sections, or say it shows them itself (`Account::settingsShownBy()`), which hides our page.
- Plugins can't add columns to Administration → Users. The app swaps that screen with
  `$panel->userResource()`: we ship `AccountUserResource`, listing staff only, which commerce's
  `StaffUserResource` extends.
- Reused from the panel and the CMS (`@api`): `Captcha`, `PasswordPolicy`, `Locales`,
  `PageCache::skipWhen()`, honeypot, `defaultRole()`, `SyncPermissions`, `MailBrand`,
  `RecordsActor`, the panel's settings table.

## Extension API (the `Account` facade)

- `Account::routes(fn (string $suffix) => ...)`: signed-in subpages under the account prefix.
- `Account::page($key, $label, $route, visible:, position:, icon:)`: the account menu.
- `Account::dashboardCard(...)`: blocks on the dashboard.
- `Account::landingUsing(fn () => $url)`: where `/account` opens (commerce: Orders).
- `Account::registrationFields(...)`, `Account::profileFields(...)`: extra fields with their rules.
- `Account::settingsSections()`: add, replace, remove sections; `Account::settingsShownBy($plugin)`.
- `Account::emails()->register(...)`: more account emails.
- Events. Before (change the data, or refuse it with a `ValidationException`): `Registering`,
  `ProfileUpdating`. After: `Registered`, `Verified`, `SignedIn`, `SignedOut`, `ProfileUpdated`,
  `PasswordChanged`, `DeletionRequested`.

## Phase 1: this package (one pull request each)

1. **Skeleton** ✅: plugin, service provider, config, `account` guard, settings storage, profiles
   table, permissions and the `member` role, Testbench workbench, Pint, Larastan max, CI, docs.
2. **Routes and layout** ✅: routes in every site language (`/account`, `/en/account`), configurable
   paths, `account.auth` / `account.guest` middleware, layout lookup (`cms.layout`, then the app's
   `resources/views/account/*`, then the package's), page cache skip, `noindex`.
3. **Sign-in flows** ✅: login, logout, register, forgot and reset password; throttling (per email +
   IP, per IP), captcha, honeypot, password policy, no account enumeration, the shared-guard staff
   check. Actions in `src/Auth/Actions`.
4. **Email verification**: signed link, resend, re-verification when the email changes.
5. **Emails**: registry, on/off switches (verify and reset can't be switched off), staff
   recipients by address and role, subject per language, publishable templates with the panel's
   mail brand, queued. Welcome, verify, reset, and to staff: new registration and deletion request.
6. **Sign in with a code and with Google**: moved from commerce, optional, off by default.
7. **My Account**: menu, dashboard and cards, landing hook, profile (name, email, phone, marketing
   consent), password, deletion request, announcement; registration and profile fields; events.
8. **Settings page**: Settings → Accounts from the section registry (general, sign-in, emails,
   announcement), with cross-checks, and the take-over API.
9. **Admin accounts**: default roles on registration, `AccountUserResource`, Administration →
   Accounts with its actions and `account.accounts.*` permissions.
10. **Finish**: `account:install`, a health check, docs, CHANGELOG, tag 0.1.0.

## Phase 2: sunrice-commerce on top of it

1. Require `sunrice/account`. Commerce's `customer` role and its sections join the account settings.
2. Remove commerce's own auth controllers, routes, views, notifications and guard. Its Orders,
   Quotes, Downloads, Points, Addresses and Wishlist pages register with `Account::routes()` and
   `Account::page()`.
3. Cart merge, guest-order linking and the customer record listen to the account events.
4. Settings → Store → Accounts shows the account settings (commerce takes the page over).
5. Commerce's Settings → Emails lists the account emails from `Account::emails()`.
6. A migration moves stored `commerce.accounts.*` settings and `commerce_customers` phone and
   consent into the account tables.
7. Upgrade notes: route names `commerce.account.*` become `account.*`, the guard `commerce` becomes
   `account` (customers sign in again once), theme CSS classes change.
