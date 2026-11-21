<?php

declare(strict_types=1);

namespace Sunrice\Account\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Sunrice\Account\Notifications\LoginCodeNotification;
use Sunrice\Account\Settings\AccountSettings;

/**
 * Emailed sign-in codes (`general.login_code`): 6 digits, valid for
 * `general.code_minutes`, usable once and from the browser that asked
 * (same IP and user agent), at most 5 tries. A new code replaces the
 * previous one. Kept hashed in `account_login_codes`.
 *
 * @api
 */
final class LoginCodes
{
    public const TABLE = 'account_login_codes';

    public const MAX_ATTEMPTS = 5;

    public function __construct(private AccountSettings $settings) {}

    public function available(): bool
    {
        return $this->settings->boolean('general.login_code');
    }

    /** Creates a code for the user (replacing an earlier one) and emails it. */
    public function send(Authenticatable $user, Request $request, string $locale): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        $minutes = $this->minutes();
        DB::table(self::TABLE)->updateOrInsert(['user_id' => $this->id($user)], [
            'code_hash' => Hash::make($code),
            'origin' => self::origin($request),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($minutes),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Notification::send($user, new LoginCodeNotification($code, $minutes, $locale));
    }

    /**
     * Checks a code and uses it up when it's right.
     *
     * @return string|null why it was refused (none, expired, origin, attempts, incorrect), or null when it's right
     */
    public function consume(Authenticatable $user, string $code, Request $request): ?string
    {
        $query = DB::table(self::TABLE)->where('user_id', $this->id($user));
        $row = $query->clone()->first();
        if ($row === null) {
            return 'none';
        }
        $attempts = is_numeric($row->attempts ?? null) ? (int) $row->attempts : 0;
        $expires = is_string($row->expires_at ?? null) ? now()->parse($row->expires_at) : now()->subSecond();
        if ($expires->isPast()) {
            $query->clone()->delete();

            return 'expired';
        }
        if ($attempts >= self::MAX_ATTEMPTS) {
            return 'attempts';
        }
        $query->clone()->increment('attempts');
        $origin = $row->origin ?? null;
        if (! is_string($origin) || ! hash_equals($origin, self::origin($request))) {
            return 'origin';
        }
        $hash = $row->code_hash ?? null;
        if (! is_string($hash) || ! Hash::check(preg_replace('/\D/', '', $code) ?? '', $hash)) {
            return $attempts + 1 >= self::MAX_ATTEMPTS ? 'attempts' : 'incorrect';
        }
        $query->clone()->delete();

        return null;
    }

    public function minutes(): int
    {
        return max(1, min(60, (int) $this->settings->string('general.code_minutes', '10')));
    }

    private static function origin(Request $request): string
    {
        return hash('sha256', (string) $request->ip().'|'.(string) $request->userAgent());
    }

    private function id(Authenticatable $user): int
    {
        $id = $user->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : 0;
    }
}
