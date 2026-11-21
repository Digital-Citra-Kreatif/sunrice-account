<?php

declare(strict_types=1);

namespace Sunrice\Account\Emails;

/**
 * The account emails (`Account::emails()`): the package's own, and those
 * plugins register. A plugin's settings page (commerce's Settings →
 * Emails) can list them all from here.
 *
 * @api
 */
final class Emails
{
    public const WELCOME = 'account_welcome';

    public const VERIFY = 'account_verify';

    public const RESET = 'account_password_reset';

    public const EXISTING = 'account_existing';

    public const LOGIN_CODE = 'account_login_code';

    public const STAFF_REGISTRATION = 'account_staff_registration';

    /** @var array<string, EmailDefinition> */
    private array $emails = [];

    public function __construct()
    {
        $this->register(
            new EmailDefinition(self::WELCOME, 'Welcome', 'account::emails.welcome', description: 'When a new account is ready (after its email is verified, while verification is on).'),
            new EmailDefinition(self::VERIFY, 'Verify email', 'account::auth.verify.mail', locked: true, description: 'The link that verifies a new account\'s email address.'),
            new EmailDefinition(self::RESET, 'Password reset', 'account::auth.reset.mail', locked: true, description: 'The link to choose a new password.'),
            new EmailDefinition(self::EXISTING, 'Account already exists', 'account::auth.register.existing', locked: true, description: 'When someone tries to register with an email that has an account.'),
            new EmailDefinition(self::LOGIN_CODE, 'Sign-in code', 'account::auth.code.mail', locked: true, description: 'A code to sign in with (while sign-in by code is on).'),
            new EmailDefinition(self::STAFF_REGISTRATION, 'New account', 'account::emails.staff_registration', 'staff', description: 'To staff: someone created an account.'),
        );
    }

    /** Adds emails, or replaces those with the same key. */
    public function register(EmailDefinition ...$emails): void
    {
        foreach ($emails as $email) {
            $this->emails[$email->key] = $email;
        }
    }

    public function get(string $key): ?EmailDefinition
    {
        return $this->emails[$key] ?? null;
    }

    /** @return array<string, EmailDefinition> */
    public function all(): array
    {
        return $this->emails;
    }
}
