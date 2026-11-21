<?php

declare(strict_types=1);

namespace Sunrice\Account\Http\Controllers;

use Illuminate\Contracts\View\View;
use Sunrice\Account\Auth\SiteGuard;
use Sunrice\Account\View\Layout;

/** My Account's first page. */
final class DashboardController
{
    public function __invoke(): View
    {
        return view(Layout::view('dashboard'), [
            'user' => SiteGuard::get()->user(),
            'seoTitle' => __('account::account.dashboard.title'),
        ]);
    }
}
