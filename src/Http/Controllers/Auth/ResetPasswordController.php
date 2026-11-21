<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Sunrice\Account\Auth\Actions\ResetPassword;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Account\View\Layout;

/** Choosing a new password from a reset link. */
final class ResetPasswordController
{
    public function show(Request $request, string $token): View
    {
        return view(Layout::view('auth.reset-password'), [
            'seoTitle' => __('account::auth.reset.choose'),
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(Request $request, ResetPassword $reset): RedirectResponse
    {
        $reset->handle($request->only(['token', 'email', 'password', 'password_confirmation']));

        return redirect()->to(AccountRoutes::url('account.login'))->with('account.status', __('account::auth.reset.done'));
    }
}
