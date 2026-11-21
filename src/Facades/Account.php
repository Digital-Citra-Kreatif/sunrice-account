<?php

declare(strict_types=1);

namespace Sunrice\Account\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Sunrice\Account\Settings\AccountSettings settings()
 * @method static void settingsShownBy(string $plugin)
 * @method static string|null settingsOwner()
 * @method static \Sunrice\Account\Emails\Emails emails()
 * @method static \Sunrice\Account\Emails\EmailSettings emailSettings()
 * @method static string guard()
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null user()
 * @method static list<string> roles(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static \Illuminate\Http\RedirectResponse redirectToSignIn(?string $returnTo = null)
 * @method static \Sunrice\Account\Profiles\Models\Profile profile(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static void routes(\Closure(string): void $routes, bool $signedIn = true)
 * @method static void page(string $key, string|\Closure(): string $label, string $route, (\Closure(): bool)|null $visible = null, int $position = 100, ?string $icon = null)
 * @method static void removePage(string $key)
 * @method static void dashboardCard(string $key, string $view, (\Closure(\Illuminate\Contracts\Auth\Authenticatable): array<string, mixed>)|null $data = null, int $position = 100)
 * @method static void landingUsing(\Closure(\Illuminate\Contracts\Auth\Authenticatable): ?string $landing)
 * @method static void registrationFields(\Closure(): list<array<string, mixed>> $fields)
 * @method static void profileFields(\Closure(): list<array<string, mixed>> $fields)
 * @method static string url(string $name, array<string, mixed> $parameters = [], ?string $locale = null)
 *
 * @see \Sunrice\Account\Account
 *
 * @api
 */
final class Account extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sunrice\Account\Account::class;
    }
}
