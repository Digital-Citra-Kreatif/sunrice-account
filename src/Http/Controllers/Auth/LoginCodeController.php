<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sunrice\Account\Auth\Actions\VerifyEmail;
use Sunrice\Account\Auth\LoginCodes;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Auth\Throttle;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Events\SignedIn;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Account\View\Layout;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Admin\Security\Captcha;

/**
 * Signing in with an emailed code: ask for one, then enter it. The code
 * proves the email, so it verifies an unverified one.
 */
final class LoginCodeController
{
    private const SESSION = 'account.code_email';

    public function send(Request $request, LoginCodes $codes): RedirectResponse
    {
        abort_unless($codes->available(), 404);
        $request->validate(['email' => ['required', 'string', 'email', 'max:190']]);
        $email = Users::normalizeEmail($request->string('email')->toString());
        // 1 a minute per email (and IP), 10 per 10 minutes per IP.
        Throttle::check('code', $request, $email, 1, 10);
        Throttle::hit('code', $request, $email, 600);
        if (! app(Captcha::class)->passes($request)) {
            return back()->withInput($request->only('email'))->withErrors(['captcha' => __('account::auth.captcha')]);
        }

        $user = Users::findByEmail($email);
        if ($user !== null && ! SiteGuard::refusesStaff($user)) {
            $codes->send($user, $request, app()->getLocale());
        }
        $request->session()->put(self::SESSION, $email);

        // The same answer for every email.
        return redirect()->to(AccountRoutes::url('account.code'))->with('account.status', __('account::auth.code.sent', ['email' => $email]));
    }

    public function show(Request $request, LoginCodes $codes): View|RedirectResponse
    {
        abort_unless($codes->available(), 404);
        $email = $request->session()->get(self::SESSION);
        if (! is_string($email)) {
            return redirect()->to(AccountRoutes::url('account.login'));
        }

        return view(Layout::view('auth.code'), ['email' => $email, 'seoTitle' => __('account::auth.code.title')]);
    }

    public function consume(Request $request, LoginCodes $codes, VerifyEmail $verify, AccountGuard $accounts): RedirectResponse
    {
        abort_unless($codes->available(), 404);
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $email = $request->session()->get(self::SESSION);
        $user = is_string($email) ? Users::findByEmail($email) : null;
        if ($user === null || SiteGuard::refusesStaff($user) || $accounts->isLocked($user)) {
            throw ValidationException::withMessages(['code' => __('account::auth.code.incorrect')]);
        }
        $error = $codes->consume($user, $request->string('code')->toString(), $request);
        if ($error !== null) {
            throw ValidationException::withMessages(['code' => __('account::auth.code.'.$error)]);
        }

        $verify->handle($user, app()->getLocale());
        $remember = $request->boolean('remember');
        SiteGuard::get()->login($user, $remember);
        $request->session()->forget(self::SESSION);
        $request->session()->regenerate();
        event(new SignedIn($user, 'code', $remember));

        return redirect()->to(Redirects::afterSignIn($request));
    }
}
