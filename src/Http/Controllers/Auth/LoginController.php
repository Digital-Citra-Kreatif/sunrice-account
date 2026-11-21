<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sunrice\Account\Auth\Actions\AuthenticateAccount;
use Sunrice\Account\Auth\Actions\SignOut;
use Sunrice\Account\Auth\Google;
use Sunrice\Account\Auth\LoginCodes;
use Sunrice\Account\Auth\Throttle;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Account\View\Layout;
use Sunrice\Admin\Security\Captcha;

/** Signing in with a password, and signing out. */
final class LoginController
{
    public function show(): View
    {
        return view(Layout::view('auth.login'), [
            'seoTitle' => __('account::auth.login.title'),
            'captcha' => app(Captcha::class)->widget(app()->getLocale()),
            'registration' => app(AccountSettings::class)->registration,
            'codes' => app(LoginCodes::class)->available(),
            'google' => Google::available(),
        ]);
    }

    public function store(Request $request, AuthenticateAccount $authenticate): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $email = $request->string('email')->toString();
        Throttle::check('login', $request, $email, 5, 30);
        if (! app(Captcha::class)->passes($request)) {
            Throttle::hit('login', $request, $email);

            return back()->withInput($request->only('email', 'remember'))->withErrors(['captcha' => __('account::auth.captcha')]);
        }
        try {
            $authenticate->handle($email, $request->string('password')->toString(), $request->boolean('remember'));
        } catch (ValidationException $e) {
            Throttle::hit('login', $request, $email);

            throw $e;
        }
        Throttle::clear('login', $request, $email);
        $request->session()->regenerate();

        return redirect()->to(Redirects::afterSignIn($request));
    }

    /** Signs out of the site only: the admin session stays. */
    public function destroy(Request $request, SignOut $signOut): RedirectResponse
    {
        $signOut->handle();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->to(Redirects::home());
    }
}
