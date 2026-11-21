<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Sunrice\Account\Auth\Actions\RegisterAccount;
use Sunrice\Account\Auth\Actions\SendVerificationLink;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\Auth\Throttle;
use Sunrice\Account\Events\SignedIn;
use Sunrice\Account\MyAccount\Fields;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Account\View\Layout;
use Sunrice\Admin\Security\Captcha;

/** The registration form (while Settings → Accounts lets visitors register). */
final class RegisterController
{
    public function show(AccountSettings $settings): View
    {
        abort_unless($settings->boolean('general.registration', true), 404);

        return view(Layout::view('auth.register'), [
            'seoTitle' => __('account::auth.register.title'),
            'captcha' => app(Captcha::class)->widget(app()->getLocale()),
            'phone' => $settings->string('general.phone', 'optional'),
            'marketing' => $settings->boolean('general.marketing_consent', true),
            'termsUrl' => $settings->string('general.terms_url'),
            'fields' => app(Fields::class)->registration(),
        ]);
    }

    public function store(Request $request, AccountSettings $settings, RegisterAccount $register, SendVerificationLink $verification): RedirectResponse
    {
        abort_unless($settings->boolean('general.registration', true), 404);
        // 5 accounts an hour from one address.
        Throttle::check('register', $request, '', 1000, 5);
        if (! app(Captcha::class)->passes($request)) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['captcha' => __('account::auth.captcha')]);
        }
        Throttle::hit('register', $request, '', 3600);

        $user = $register->handle($request->only(['name', 'email', 'phone', 'password', 'password_confirmation', 'accepts_marketing', 'terms', 'fields']), app()->getLocale());
        if ($user === null) {
            return redirect()->to(AccountRoutes::url('account.login'))->with('account.status', __('account::auth.register.check_email'));
        }

        if ($settings->boolean('general.verify_email', true)) {
            // The same answer as for a taken email: the link signs the account in.
            $verification->handle($user, app()->getLocale());

            return redirect()->to(AccountRoutes::url('account.login'))->with('account.status', __('account::auth.register.check_email'));
        }
        SiteGuard::get()->login($user);
        $request->session()->regenerate();
        event(new SignedIn($user, 'registration'));

        return redirect()->to(AccountRoutes::url('account.dashboard'))->with('account.status', __('account::auth.register.welcome'));
    }
}
