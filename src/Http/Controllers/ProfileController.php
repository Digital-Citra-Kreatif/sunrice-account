<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\MyAccount\Fields;
use Sunrice\Account\Profiles\Actions\ChangePassword;
use Sunrice\Account\Profiles\Actions\RequestDeletion;
use Sunrice\Account\Profiles\Actions\UpdateProfile;
use Sunrice\Account\Profiles\Models\Profile;
use Sunrice\Account\Settings\AccountSettings;
use Sunrice\Account\View\Layout;

/** My Account → Profile: details, password, and asking for the account to be deleted. */
final class ProfileController
{
    public function show(AccountSettings $settings, Fields $fields): View
    {
        $user = $this->user();
        $profile = Profile::forUser($user);

        return view(Layout::view('profile'), [
            'user' => $user,
            'profile' => $profile,
            'fields' => $fields->profile(),
            'phone' => $settings->phone,
            'marketing' => $settings->marketingConsent,
            'deletion' => $settings->deletionRequests,
            'seoTitle' => __('account::account.profile.title'),
        ]);
    }

    public function update(Request $request, UpdateProfile $update): RedirectResponse
    {
        $sent = $update->handle($this->user(), $request->only(['name', 'email', 'phone', 'accepts_marketing', 'current_password', 'fields']), app()->getLocale());

        return back()->with('account.status', __($sent ? 'account::account.profile.verify_new_email' : 'account::account.profile.saved'));
    }

    public function password(Request $request, ChangePassword $change): RedirectResponse
    {
        try {
            $change->handle($this->user(), $request->only(['current_password', 'password', 'password_confirmation']));
        } catch (ValidationException $e) {
            // Its own errors: the profile form above has a current password field too.
            throw $e->errorBag('password');
        }
        $request->session()->regenerate();

        return back()->with('account.status', __('account::account.password.saved'));
    }

    public function requestDeletion(Request $request, AccountSettings $settings, RequestDeletion $request_): RedirectResponse
    {
        abort_unless($settings->deletionRequests, 404);
        $request->validate(['confirm' => ['accepted']]);
        $request_->handle($this->user());

        return back()->with('account.status', __('account::account.delete.requested'));
    }

    private function user(): Model&Authenticatable
    {
        $user = SiteGuard::get()->user();
        abort_unless($user instanceof Model, 401);

        return $user;
    }
}
