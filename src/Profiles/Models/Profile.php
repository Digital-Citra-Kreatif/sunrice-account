<?php

declare(strict_types=1);

namespace Sunrice\Account\Profiles\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A user's site account details, beside the app's users table: phone,
 * language, marketing consent, a deletion request, and `meta` for what
 * plugins add to the profile and registration forms (by their own keys).
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $phone
 * @property string|null $locale
 * @property bool $accepts_marketing
 * @property Carbon|null $marketing_consent_at
 * @property bool $password_set false for accounts made without a password (emailed code, Google) until they set one
 * @property Carbon|null $deletion_requested_at
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @api
 */
class Profile extends Model
{
    protected $table = 'account_profiles';

    protected $guarded = [];

    protected $attributes = [
        'accepts_marketing' => false,
        'password_set' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'accepts_marketing' => 'boolean',
            'marketing_consent_at' => 'datetime',
            'password_set' => 'boolean',
            'deletion_requested_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /** The profile of a user (created on first need). */
    public static function forUser(Authenticatable $user): self
    {
        $id = $user->getAuthIdentifier();
        if (! is_numeric($id)) {
            throw new LogicException('Account profiles need users with numeric ids.');
        }

        return static::query()->firstOrCreate(['user_id' => (int) $id]);
    }

    /** A value a plugin keeps in `meta`: $profile->metaValue('commerce.tax_id'). */
    public function metaValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta ?? [], $key, $default);
    }
}
