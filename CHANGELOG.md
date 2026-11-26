# Changelog

## Unreleased

- **Administration → Accounts** (`Admin\AccountResource`): the site's accounts in the admin, behind
  `account.accounts.view`, `.update`, `.delete` and `.export`. Staff change a name, address, phone
  and site roles, email a password link, mark an address verified, end sessions, see and dismiss
  deletion requests, and export the list. There is no "New account", and staff are never listed.
- **Remove personal data** (`Profiles\Actions\AnonymizeAccount`, the screen's Delete): the user row
  stays for what points at it, without the person. Name and email become placeholders, the
  password a random one; phone, language, consent, plugins' profile fields, roles, Google links,
  sign-in codes, reset links and sessions go. Plugins clear their own records on the new
  `Anonymized` event. Migration `add_anonymized_at_to_account_profiles_table`: run
  `php artisan migrate`.
- `Admin\Accounts` tells staff from accounts (`isStaff()`, and `staff()`, `accounts()`, `users()`
  for queries), and `Admin\AccountUserResource` is Administration → Users with staff only: set it
  with `$panel->userResource(AccountUserResource::class)`.
- `Account::adminCard()`: a plugin's cards beside an account's form in the admin.

## 0.1.0 (2026-11-21)

- **Accessibility**: a field's error is tied to its input (`aria-invalid`, `aria-describedby`;
  `partials.aria`), and a form's errors are announced once, by a summary above it, instead of one
  alert each. `/account` marks its menu item current. Input borders have enough contrast (3:1) in
  light and dark, a field with an error has a red border, and links and buttons show a focus ring.
  The announcement's title is no longer a heading before the page's own. Plugins' checkbox fields
  show their help and errors; their selects' empty option says "Choose…".
- **Settings → Accounts**: staff emails go only to roles that open the admin (the super admin's,
  and roles with `sunrice.access`); a customer role ticked there would have emailed every account
  on every sign-up. At most 100 people get one, by the panel guard's roles. The account address
  can't be one where the app already has pages; help texts show the addresses as they are now
  (and Google's callback address); changing the address warns that Google's callback changes; the
  verification help says codes and Google prove an address too; an announcement needs a message.
- **No more clashes with the app** (breaking, before 1.0):
  - Route names are `sunrice.account.*` (were `account.*`, which took over an app's own
    `account.login`, `account.profile`… while its `/account/*` URLs still won): use
    `Account::url('sunrice.account.dashboard')`. Middleware aliases are `sunrice.account.auth`,
    `sunrice.account.guest`, `sunrice.account.verified`, `sunrice.account.noindex`.
  - An app's copies of account views go in `resources/views/vendor/account/` (Laravel's place;
    `vendor:publish --tag=sunrice-account-views` copies them there), emails' bodies in
    `resources/views/vendor/account/emails/{key}.blade.php`. An app's own `resources/views/account/`
    is no longer taken for the package's views. Move copies you made.
  - The account pages' CSS is pushed to the layout's `head` stack, a theme's too, once
    (`partials.push-styles`); `sunrice-account.styles` (`SUNRICE_ACCOUNT_STYLES`) turns it off for
    a theme that styles the `account-*` classes itself.
  - Docs: while the site shares the admin's guard, signing out of the site signs out of the admin.
- **Sign-in flows that no longer leave people stuck**:
  - `account.verified` sent a signed-in, unverified account to a page for guests, which bounced it
    to My Account with no word. The verify page now works signed in: it sends the link, says where
    it went and offers "Send the link again" (and sign-out); a verified account goes to My Account.
  - "Send a new code" waited 10 minutes instead of 1, without saying so: it's one a minute per
    email, and the code page shows why when it's too soon. It says where the code went, and keeps
    "Keep me signed in".
  - An expired, used or changed verification link goes to the verify page with what happened and a
    way to a new one, instead of a bare 403.
  - A reset link that no longer works says so above the form, with "Ask for a new link"; the page
    links to a new link and back to sign in, and its title matches it.
  - The sign-in page asks for the email once: "Email me a sign-in code" is a second button of the
    same form. "Didn't get the email to verify…" shows only while emails are verified; staff told
    to use the admin's sign-in get a link to it.
  - The profile's two forms keep their errors apart (the password form uses the `password` error
    bag); unticked checkboxes stay unticked after an error; messages name fields in the page's
    language ("Nama wajib diisi").
  - A new account goes where it was going (a checkout), or to the "after signing in" page.
  - /register while registration is off goes to sign-in with a message instead of a 404.
- **Security fixes** (see [Keeping accounts safe](docs/index.md#keeping-accounts-safe)):
  - An unverified account made with someone else's address is taken back when its owner proves the
    address with an emailed code or Google: its password is cleared and its other sessions end
    (`VerifyEmail::claim()`). Before, whoever made it kept their password. A changed profile address
    is unverified, whether or not emails must be verified.
  - The verification link signs in only the first time, and never over someone signed in (it could
    be reused for 24 hours, or swap a visitor into another account).
  - Locked accounts and "Sign out everywhere" end site sessions too (`account.auth` checks
    `AccountGuard::sessionAllowed()`; site sign-ins set `AccountGuard::LOGIN_AT`). Changing or
    resetting the password signs out the account's other sessions.
  - Sign-in takes as long for an unknown email; every code-check failure reads the same; the
    profile says an address is taken only after the current password is right.
  - New limits: 20 wrong passwords an hour per email from any IP, 5 codes an hour per email, 5 wrong
    current passwords in 10 minutes on the profile page.
  - Visitors' names in staff emails can't become Markdown links (`AccountEmail::plain()`).
  - `Account::redirectToSignIn()` takes only an address on this site (a path or a URL of it), and
    the return address after sign-in is checked more strictly (no backslashes or user info).
- `Account::roles($user)`: the names of an account's roles, sorted. `Account::redirectToSignIn(?string
  $returnTo = null)`: sends a guest to sign in and back to the page they asked for (or `$returnTo`)
  afterwards; for plugins with members-only pages.
- The package's layout styles a link made a button (`<a class="account-button account-button--primary">`) as
  one: its text no longer takes the link colour. `account-actions` lays out a row of buttons and
  links.
- The package's own site layout (for a site without one) styles the account pages: plain, light
  and dark, phone-friendly CSS for the `account-*` classes, inline with the site's CSP nonce. A
  site's own layout styles them itself.
- Password sign-in has no honeypot any more: password managers fill and send it at once, which
  the honeypot's timing took for a bot (a blank page). It's throttled per email and IP; the forms
  that send email (register, forgot password, sign-in code, verification) keep the honeypot.
- The package: `sunrice/account`, a Sunrice plugin (`AccountPlugin`, id `account`, after the CMS).
  - The site's own session guard, `account` (`sunrice-account.auth.guard`), defined on the user
    provider of the panel's guard unless `config/auth.php` defines it.
  - Settings → Accounts (`AccountSettings`, `Account::settings()`): a panel settings section
    (`accounts`) with typed settings in four parts (General, Sign-in, Announcement, Emails),
    checked on save: new accounts' roles can't open the admin, the account address, the links
    after sign-in and to the terms stay on the site, Google needs its credentials. Saving a new
    account address clears the route cache. AI agents get it with the panel's settings tools.
  - `Account::settingsShownBy($plugin)`: a plugin shows the account settings on a page of its own
    (with `SettingsFields::make(AccountSettings::class, AccountSettings::form(new Form))`) and
    Settings → Accounts leaves the menu. `AccountSettings::sections()` gives the form's parts.
  - Profiles (`account_profiles`, `Account::profile($user)`): phone, language, marketing consent,
    deletion request and `meta` for plugins.
  - The `account.settings.accounts` permission (with the settings section) and the `member` role (no admin permissions), created
    by `sunrice:roles`.
- Account pages and their routes:
  - Under the account prefix (`sunrice-account.paths.prefix`, `account`; Settings → Accounts'
    `path` wins), in every language of the site: `/account`, `/en/account`. Route names
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
  - New accounts get the default roles (`defaultRoles`) and a profile (phone, language,
    marketing consent). Settings `phone` (off/optional/required), `marketingConsent`,
    `termsUrl` and `redirectAfterLogin`.
  - Events: `Registering` (change the data, or refuse with a `ValidationException`), `Registered`,
    `SignedIn`, `SignedOut`, `PasswordReset`. Actions in `Sunrice\Account\Auth\Actions`.
- Email verification (`verifyEmail`, on by default):
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
  - Settings per email (`welcomeEmail`, `welcomeSubject`…): a switch (the verify, reset,
    existing-account and sign-in code emails have none), the subject per language (`:site`,
    `{name}` placeholders), and for staff emails addresses and roles (default: super admins and
    administrators). A plugin's email gives its own settings (`EmailDefinition` `settings:`).
  - `AccountEmail`, the base of every account email: queued, in one language, with the site's mail
    brand (`Panel::mailBrand()`) in `account::mail.notification`; an app replaces an email's body
    with `resources/views/account/emails/{key}.blade.php`.
  - New: the welcome email (at registration while emails aren't verified, else once verified) and
    the staff email of a new account (to the chosen roles' users and addresses, each once, in the
    site's main language).
- Sign in with an emailed code (`loginCode`, off by default), with
  `spatie/laravel-one-time-passwords`: the user model needs its `HasOneTimePasswords` trait, and its
  `one_time_passwords` migration (`php artisan vendor:publish --tag=one-time-passwords-migrations`).
  6 digits, valid for `codeMinutes` (10), once, from the browser that asked, 5 tries a
  minute per account plus 10 per 10 minutes per email and IP; one code a minute per email; the same
  answer for any email. A code verifies the email. `LoginCodes`, email `account_login_code` (locked).
- Sign in with Google (`googleLogin`, off by default; `laravel/socialite`, credentials
  from `services.google` or `sunrice-account.google`): a linked account signs in; a verified
  Google email links the account that has it; otherwise an account is created (verified, default
  roles, `password_set` false) while registration is open. Links in `account_social_accounts`.
  Callback `/account/login/google/callback` (`account.google.callback`).
- Staff while the site shares the admin's guard, and locked accounts, can't sign in by code or
  Google. `SignedIn::$method` is `code` or `google` for them.
- My Account:
  - A menu (`Account::page()`, `Account::removePage()`; `MyAccount\Navigation`) with the package's
    Overview (`/account/overview`) and Profile, and plugins' pages in `position` order, while their
    route exists and their `visible` check says yes. Sign out from the menu.
  - `/account` opens where a plugin says (`Account::landingUsing()`), else the announcement while
    it's the first tab, else the dashboard, which shows plugins' cards (`Account::dashboardCard()`).
  - Profile (`/account/profile`): name, email (a new one needs the current password and is verified
    again while emails are verified), phone, marketing consent and plugins' fields; changing or
    first choosing a password (a new remember token signs out other devices); asking for the
    account to be deleted (`deletionRequests`), which emails staff
    (`account_staff_deletion`). Actions `UpdateProfile`, `ChangePassword`, `RequestDeletion`; events
    `ProfileUpdating` (change or refuse), `ProfileUpdated` (`$changed`), `PasswordChanged`,
    `DeletionRequested`.
  - Plugins' fields on the registration form and the profile page (`Account::registrationFields()`,
    `Account::profileFields()`: text, textarea, select, checkbox, with their rules), kept in the
    profile's `meta`.
  - An announcement (settings `announcement`, `announcementPosition` tab/above/below, a title and
    message per language), escaped, line breaks kept.
