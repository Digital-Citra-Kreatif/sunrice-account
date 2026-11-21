<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Sunrice\Account\Auth\Actions\SendPasswordResetLink;
use Sunrice\Account\Auth\Throttle;
use Sunrice\Account\View\Layout;
use Sunrice\Admin\Security\Captcha;

/** Asking for a password reset link. */
final class ForgotPasswordController
{
    public function show(): View
    {
        return view(Layout::view('auth.forgot-password'), [
            'seoTitle' => __('account::auth.reset.title'),
            'captcha' => app(Captcha::class)->widget(app()->getLocale()),
        ]);
    }

    public function store(Request $request, SendPasswordResetLink $send): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:190']]);
        $email = $request->string('email')->toString();
        Throttle::check('reset', $request, $email, 3, 10);
        Throttle::hit('reset', $request, $email, 600);
        if (! app(Captcha::class)->passes($request)) {
            return back()->withInput($request->only('email'))->withErrors(['captcha' => __('account::auth.captcha')]);
        }
        $send->handle($email, app()->getLocale());

        // The same answer whether or not the email has an account.
        return back()->with('account.status', __('account::auth.reset.sent'));
    }
}
