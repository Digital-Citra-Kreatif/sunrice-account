<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Sunrice\Account\Auth\Actions\SignInWithGoogle;
use Sunrice\Account\Auth\Google;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Cms\Support\Locales;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/** Signing in with Google: off to Google, and back on one fixed callback URL. */
final class GoogleController
{
    private const SESSION = 'account.google_locale';

    public function redirect(Request $request): SymfonyRedirect
    {
        abort_unless(Google::available(), 404);
        $request->session()->put(self::SESSION, app()->getLocale());

        return Google::driver()->redirect();
    }

    public function callback(Request $request, SignInWithGoogle $signIn): RedirectResponse
    {
        abort_unless(Google::available(), 404);
        $stored = $request->session()->pull(self::SESSION);
        $locale = is_string($stored) && Locales::has($stored) ? $stored : Locales::default();
        app()->setLocale($locale);
        $login = AccountRoutes::url('sunrice.account.login', [], $locale);
        try {
            $google = Google::driver()->user();
        } catch (Throwable) {
            return redirect()->to($login)->withErrors(['email' => __('account::auth.google.failed')]);
        }

        $result = $signIn->handle($google, $locale);
        if (is_string($result)) {
            return redirect()->to($login)->withErrors(['email' => __('account::auth.google.'.$result)]);
        }
        $request->session()->regenerate();

        return redirect()->to(Redirects::afterSignIn($request));
    }
}
