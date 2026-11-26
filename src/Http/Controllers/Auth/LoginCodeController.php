<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sunrice\Account\Auth\Actions\CreateProvenAccount;
use Sunrice\Account\Auth\Actions\VerifyEmail;
use Sunrice\Account\Auth\LoginCodes;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Auth\Throttle;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Events\SignedIn;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Account\Settings\AccountSettings;
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

    /** Whether "Keep me signed in" was ticked when the code was asked for. */
    private const REMEMBER = 'account.code_remember';

    public function send(Request $request, LoginCodes $codes): RedirectResponse
    {
        abort_unless($codes->available(), 404);
        $request->validate(['email' => ['required', 'string', 'email', 'max:190']]);
        $email = Users::normalizeEmail($request->string('email')->toString());
        // 1 a minute per email (and IP), 10 per 10 minutes per IP.
        Throttle::check('code', $request, $email, 1, 10);
        // And 5 an hour for one email from any IP: nobody can flood an inbox, or keep replacing its code.
        Throttle::account('code-mail', $email, 'email', 5, 3600);
        Throttle::hit('code', $request, $email, 60, 600);
        Throttle::account('code-mail', $email, 'email', 5, 3600, hit: true);
        if (! app(Captcha::class)->passes($request)) {
            return back()->withInput($request->only('email', 'remember'))->withErrors(['captcha' => __('account::auth.captcha')]);
        }

        // A code for every address that may come in: an account's (staff aside, while the site shares
        // the admin's guard), and while visitors can register, any other, whose account the right code makes.
        $user = Users::findByEmail($email);
        if ($user !== null ? ! SiteGuard::refusesStaff($user) : app(AccountSettings::class)->registration) {
            $codes->send($email, $request, app()->getLocale());
        } else {
            $codes->forget($request);
        }
        $request->session()->put(self::SESSION, $email);
        $request->session()->put(self::REMEMBER, $request->boolean('remember'));

        // The same answer for every email.
        return redirect()->to(AccountRoutes::url('sunrice.account.code'))->with('account.status', __(app(AccountSettings::class)->registration ? 'account::auth.code.sent_open' : 'account::auth.code.sent', ['email' => $email]));
    }

    public function show(Request $request, LoginCodes $codes): View|RedirectResponse
    {
        abort_unless($codes->available(), 404);
        $email = $request->session()->get(self::SESSION);
        if (! is_string($email)) {
            return redirect()->to(AccountRoutes::url('sunrice.account.login'));
        }

        return view(Layout::view('auth.code'), [
            'email' => $email,
            // While visitors can register, every address gets a code: say so plainly.
            'open' => app(AccountSettings::class)->registration,
            'remember' => (bool) $request->session()->get(self::REMEMBER, false),
            'seoTitle' => __('account::auth.code.title'),
        ]);
    }

    public function consume(Request $request, LoginCodes $codes, VerifyEmail $verify, CreateProvenAccount $create, AccountGuard $accounts): RedirectResponse
    {
        abort_unless($codes->available(), 404);
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $email = $request->session()->get(self::SESSION);
        // On top of the codes' own limits: 10 tries per 10 minutes per email + IP, 30 per IP.
        Throttle::check('code-check', $request, is_string($email) ? $email : '', 10, 30, 'code');
        Throttle::hit('code-check', $request, is_string($email) ? $email : '', 600);

        // One answer for every failure (wrong, expired, used up, too many tries, no code sent): an
        // address that got no code can't be told apart from a mistyped one.
        $incorrect = ValidationException::withMessages(['code' => __('account::auth.code.incorrect')]);
        $pending = $codes->pending($request);
        if ($pending === null || $pending['email'] !== $email || $codes->consume($request->string('code')->toString(), $request) !== null) {
            throw $incorrect;
        }
        $user = Users::findByEmail($pending['email']);
        if ($user !== null && (SiteGuard::refusesStaff($user) || $accounts->isLocked($user))) {
            throw $incorrect;
        }
        if ($user === null) {
            // A new address, proven: its account is made here, with nothing more to fill in.
            if (! app(AccountSettings::class)->registration) {
                throw $incorrect;
            }
            $user = $create->handle($pending['email'], null, app()->getLocale());
            $method = 'registration';
        } else {
            // The code proves the address: an unverified account with it loses what someone else may have set.
            $verify->claim($user, app()->getLocale());
            $method = 'code';
        }

        $remember = $request->boolean('remember');
        SiteGuard::get()->login($user, $remember);
        $request->session()->forget([self::SESSION, self::REMEMBER]);
        $request->session()->regenerate();
        event(new SignedIn($user, $method, $remember));

        $to = redirect()->to(Redirects::afterSignIn($request));

        return $method === 'registration' ? $to->with('account.status', __('account::auth.register.welcome')) : $to;
    }
}
