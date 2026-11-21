<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Sunrice\Account\Auth\Actions\SendVerificationLink;
use Sunrice\Account\Auth\Actions\VerifyEmail;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Auth\Throttle;
use Sunrice\Account\Auth\Users;
use Sunrice\Account\Auth\Verification;
use Sunrice\Account\Events\SignedIn;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Account\View\Layout;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Admin\Security\Captcha;
use Sunrice\Cms\Support\Locales;

/** Email verification: the link, and asking for a new one. */
final class VerificationController
{
    public function show(): View
    {
        return view(Layout::view('auth.verify'), [
            'seoTitle' => __('account::auth.verify.title'),
            'captcha' => app(Captcha::class)->widget(app()->getLocale()),
        ]);
    }

    /** Sends a new link to an unverified account; the answer is the same for any email. */
    public function send(Request $request, SendVerificationLink $send): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:190']]);
        $email = $request->string('email')->toString();
        Throttle::check('verify', $request, $email, 3, 10);
        Throttle::hit('verify', $request, $email, 600);
        if (! app(Captcha::class)->passes($request)) {
            return back()->withInput($request->only('email'))->withErrors(['captcha' => __('account::auth.captcha')]);
        }
        $user = Users::findByEmail($email);
        if ($user !== null) {
            $send->handle($user, app()->getLocale());
        }

        return back()->with('account.status', __('account::auth.verify.sent'));
    }

    /**
     * The link from the email: verifies the address and signs the account
     * in, in the language the link was sent in. Staff while the site shares
     * the admin's guard, and locked accounts, are verified but not signed in.
     */
    public function verify(Request $request, string $id, string $hash, VerifyEmail $verify, AccountGuard $accounts): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $user = Users::model()::query()->find($id);
        if (! $user instanceof Authenticatable || ! hash_equals(Verification::hash($user), $hash)) {
            abort(403);
        }

        $lang = $request->string('lang')->toString();
        $locale = Locales::has($lang) ? $lang : Locales::default();
        app()->setLocale($locale);
        $verify->handle($user, $locale);

        if (SiteGuard::refusesStaff($user) || $accounts->isLocked($user)) {
            return redirect()->to(AccountRoutes::url('account.login', [], $locale))->with('account.status', __('account::auth.verify.done'));
        }
        SiteGuard::get()->login($user);
        $request->session()->regenerate();
        event(new SignedIn($user, 'verification'));

        return redirect()->to(AccountRoutes::url('account.dashboard', [], $locale))->with('account.status', __('account::auth.verify.done'));
    }
}
