<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\MyAccount\Announcement;
use Sunrice\Account\MyAccount\Dashboard;
use Sunrice\Account\Routing\AccountRoutes;
use Sunrice\Account\View\Layout;

/**
 * /account: where a plugin says it opens (Account::landingUsing()), else
 * the announcement while it's the first tab, else the dashboard and its
 * cards. The dashboard itself is always at /account/overview.
 */
final class DashboardController
{
    public function landing(Dashboard $dashboard, Announcement $announcement): View|RedirectResponse
    {
        $user = SiteGuard::get()->user();
        $landing = $user === null ? null : $dashboard->landing($user);
        if ($landing !== null) {
            return redirect()->to($landing);
        }
        if ($announcement->position() === 'tab') {
            return redirect()->to(AccountRoutes::url('account.announcement'));
        }

        return $this->overview($dashboard);
    }

    public function overview(Dashboard $dashboard): View
    {
        $user = SiteGuard::get()->user();

        return view(Layout::view('dashboard'), [
            'user' => $user,
            'cards' => $user === null ? [] : $dashboard->cards($user),
            'seoTitle' => __('account::account.dashboard.title'),
        ]);
    }

    public function announcement(Announcement $announcement): View
    {
        abort_unless($announcement->position() === 'tab', 404);

        return view(Layout::view('announcement'), ['announcement' => $announcement, 'seoTitle' => $announcement->title()]);
    }
}
