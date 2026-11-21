<?php

declare(strict_types=1);

namespace Sunrice\Account\Routing;

use Closure;
use Illuminate\Support\Facades\Route;
use Sunrice\Account\Http\Controllers\Auth\GoogleController;
use Sunrice\Account\Http\Controllers\Auth\VerificationController;
use Sunrice\Account\Http\Middleware\Authenticate;
use Sunrice\Account\Http\Middleware\ForgetLocaleParameter;
use Sunrice\Account\Http\Middleware\NoIndex;
use Sunrice\Account\Http\Middleware\SetSiteLocale;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Cms\Support\Locales;

/**
 * The account pages' routes, in every language of the site, like the
 * CMS's own pages: once without a prefix (the language at the plain
 * addresses) and once under /{locale} for the others. Route names get the
 * suffix '' or '.locale':
 *
 *     AccountRoutes::localized(function (string $suffix) {
 *         Route::get('cart', CartController::class)->name('shop.cart'.$suffix);
 *     });
 *
 * Pages under the account prefix are added with Account::routes().
 *
 * @api
 */
final class AccountRoutes
{
    public const LOCALE_SUFFIX = '.locale';

    /** @var list<array{routes: Closure(string): void, signedIn: bool}> */
    private array $extensions = [];

    private bool $registered = false;

    /**
     * Routes in every language of the site, with the language set from the
     * address.
     *
     * @param  Closure(string): void  $routes  receives the route-name suffix
     */
    public static function localized(Closure $routes): void
    {
        $middleware = ['web', SetSiteLocale::class, ForgetLocaleParameter::class];

        Route::middleware($middleware)->group(fn () => $routes(''));

        // Each other language under its address segment (Settings → Languages: by default its code).
        $others = array_values(array_filter(Locales::segments(), fn (string $segment): bool => $segment !== ''));
        if ($others !== []) {
            Route::middleware($middleware)
                ->prefix('{locale}')
                ->where(['locale' => '(?:'.implode('|', array_map(fn (string $segment): string => preg_quote($segment, '/'), $others)).')'])
                ->group(fn () => $routes(self::LOCALE_SUFFIX));
        }
    }

    /**
     * An account URL in the current (or given) language:
     * AccountRoutes::url('account.dashboard').
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function url(string $name, array $parameters = [], ?string $locale = null, bool $absolute = true): string
    {
        $locale ??= app()->getLocale();
        $segment = Locales::has($locale) ? (Locales::segments()[$locale] ?? $locale) : '';
        if ($segment !== '' && Route::has($name.self::LOCALE_SUFFIX)) {
            return route($name.self::LOCALE_SUFFIX, ['locale' => $segment, ...$parameters], $absolute);
        }

        return route($name, $parameters, $absolute);
    }

    /** Where the account pages live: the settings page's path, else config paths.prefix ("account"). */
    public static function prefix(): string
    {
        $path = trim(app(AccountSettings::class)->string('general.path'), '/ ');

        return $path !== '' ? $path : self::path('prefix');
    }

    /** A configured path under the prefix: AccountRoutes::path('login') → "login". */
    public static function path(string $key): string
    {
        $path = config("sunrice-account.paths.{$key}");

        return is_string($path) && trim($path, '/ ') !== '' ? trim($path, '/ ') : $key;
    }

    /**
     * Pages under the account prefix, in every language: signed-in pages
     * (`account.auth`) unless $signedIn is false. Before the app has
     * booted they are added with the package's own; after, at once.
     *
     * @param  Closure(string): void  $routes  receives the route-name suffix
     */
    public function extend(Closure $routes, bool $signedIn = true): void
    {
        $extension = ['routes' => $routes, 'signedIn' => $signedIn];
        if (! $this->registered) {
            $this->extensions[] = $extension;

            return;
        }
        $this->group([$extension]);
        Route::getRoutes()->refreshNameLookups();
    }

    /** The package's routes and the extensions added so far (once, after the app has booted). */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;
        $this->group([['routes' => function (string $suffix): void {
            require __DIR__.'/../../routes/account.php';
        }, 'signedIn' => false], ...$this->extensions]);
        $this->extensions = [];
        // The verification link: one fixed address (signed), its language in ?lang=.
        Route::middleware(['web', NoIndex::class])->prefix(self::prefix())
            ->get(self::path('verify').'/{id}/{hash}', [VerificationController::class, 'verify'])
            ->whereNumber('id')->name('account.verify');
        // Google's callback: one fixed address (the one given to Google), its language from the session.
        Route::middleware(['web', NoIndex::class])->prefix(self::prefix())
            ->get(self::path('login').'/google/callback', [GoogleController::class, 'callback'])
            ->name('account.google.callback');
    }

    /** @param  list<array{routes: Closure(string): void, signedIn: bool}>  $extensions */
    private function group(array $extensions): void
    {
        self::localized(function (string $suffix) use ($extensions): void {
            Route::middleware(NoIndex::class)->prefix(self::prefix())->group(function () use ($extensions, $suffix): void {
                foreach ($extensions as $extension) {
                    Route::middleware($extension['signedIn'] ? [Authenticate::class] : [])
                        ->group(fn () => ($extension['routes'])($suffix));
                }
            });
        });
    }
}
