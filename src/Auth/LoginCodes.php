<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Sunrice\Account\Notifications\LoginCodeNotification;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Admin\Auth\EmailCodes;

/**
 * Emailed sign-in codes (Settings → Accounts → Sign-in), with the panel's
 * EmailCodes: a code is 6 digits, for one address, valid for
 * `codeMinutes`, usable once, in the browser session that asked for it; a
 * new code replaces the previous one; 5 wrong tries end a code, and 10 for
 * one address lock it for 15 minutes whatever codes it asks for.
 *
 * A code is for an address, not an account: the right code proves the
 * address, and the controller then signs in its account or, for a new
 * address while registration is open, creates it.
 *
 * @api
 */
final class LoginCodes
{
    /** The purpose of these codes among the panel's EmailCodes. */
    public const PURPOSE = 'account-login';

    public function __construct(private AccountSettings $settings, private EmailCodes $codes) {}

    public function available(): bool
    {
        return $this->settings->loginCode;
    }

    /**
     * Starts a code for the address in this session (replacing an earlier
     * one) and emails it there.
     *
     * @param  array<string, mixed>  $data  kept with it, given back by pending()
     */
    public function send(string $email, Request $request, string $locale, array $data = []): void
    {
        $email = Users::normalizeEmail($email);
        $minutes = $this->minutes();
        $code = $this->codes->issue($request, self::PURPOSE, $email, $data, $minutes);
        $notification = new LoginCodeNotification($code, $minutes, $locale);
        // To the account when there is one (its own notification channels), else to the address.
        $user = Users::findByEmail($email);
        $user !== null ? Notification::send($user, $notification) : Notification::route('mail', $email)->notify($notification);
    }

    /** Ends the session's pending code: nothing typed afterwards is right. */
    public function forget(Request $request): void
    {
        $this->codes->forget($request, self::PURPOSE);
    }

    /**
     * The address this session's pending code was sent to, and what was kept with it.
     *
     * @return array{email: string, data: array<string, mixed>}|null
     */
    public function pending(Request $request): ?array
    {
        $pending = $this->codes->pending($request, self::PURPOSE);

        return $pending === null ? null : ['email' => (string) $pending['user_id'], 'data' => $pending['data']];
    }

    /**
     * Checks a code and uses it up when it's right. Read pending() first:
     * the right code ends it.
     *
     * @return string|null why it was refused (wrong, expired, locked, missing), or null when it's right
     */
    public function consume(string $code, Request $request): ?string
    {
        $result = $this->codes->check($request, self::PURPOSE, $code);

        return $result === 'ok' ? null : $result;
    }

    public function minutes(): int
    {
        return max(1, min(60, $this->settings->codeMinutes));
    }
}
