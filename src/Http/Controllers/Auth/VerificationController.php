<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
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
    /**
     * Ask for a new link: a guest enters their email; a signed-in account
     * still to verify its own sees where the link went and can send it
     * again (a verified one goes to My Account).
     */
    public function show(): View|RedirectResponse
    {
        $user = SiteGuard::get()->user();
        if ($user !== null && Verification::verified($user)) {
            return redirect()->to(AccountRoutes::url('sunrice.account.dashboard'));
        }
        $email = $user?->getAttribute('email');

        return view(Layout::view('auth.verify'), [
            'seoTitle' => __('account::auth.verify.title'),
            'captcha' => app(Captcha::class)->widget(app()->getLocale()),
            // Signed in: the address the link goes to.
            'email' => is_string($email) ? $email : null,
        ]);
    }

    /** Sends a new link to an unverified account; the answer is the same for any email. */
    public function send(Request $request, SendVerificationLink $send): RedirectResponse
    {
        $own = SiteGuard::get()->user()?->getAttribute('email');
        if (is_string($own)) {
            $request->merge(['email' => $own]);
        }
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
     * The link from the email: verifies the address and, the first time,
     * signs the account in, in the language the link was sent in. Opened
     * again, while someone else is signed in, for staff while the site
     * shares the admin's guard, or for a locked account, it verifies only
     * and sends them to sign in.
     */
    public function verify(Request $request, string $id, string $hash, VerifyEmail $verify, AccountGuard $accounts): RedirectResponse
    {
        $lang = $request->string('lang')->toString();
        $locale = Locales::has($lang) ? $lang : Locales::default();
        app()->setLocale($locale);
        $user = URL::hasValidSignature($request) ? Users::model()::query()->find($id) : null;
        if (! $user instanceof Authenticatable || ! hash_equals(Verification::hash($user), $hash)) {
            // Expired, changed, or for an address the account no longer has: ask for a new one.
            return redirect()->to(AccountRoutes::url('sunrice.account.verification.notice', [], $locale))->with('account.error', __('account::auth.verify.invalid'));
        }

        $verifiedNow = $verify->handle($user, $locale);

        // It signs in only the first time, and never over someone signed in: a link opened again,
        // or sent to someone else, isn't a way into the account (or a way to swap theirs for it).
        $current = SiteGuard::get()->user();
        if ($current instanceof Model && $user->is($current)) {
            return redirect()->to(AccountRoutes::url('sunrice.account.dashboard', [], $locale))->with('account.status', __('account::auth.verify.done'));
        }
        if (! $verifiedNow || $current !== null || SiteGuard::refusesStaff($user) || $accounts->isLocked($user)) {
            return redirect()->to(AccountRoutes::url('sunrice.account.login', [], $locale))->with('account.status', __('account::auth.verify.done'));
        }
        SiteGuard::get()->login($user);
        $request->session()->regenerate();
        event(new SignedIn($user, 'verification'));

        return redirect()->to(AccountRoutes::url('sunrice.account.dashboard', [], $locale))->with('account.status', __('account::auth.verify.done'));
    }
}
