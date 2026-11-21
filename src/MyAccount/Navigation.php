<?php

declare(strict_types=1);

namespace Sunrice\Account\MyAccount;

use Closure;
use Illuminate\Support\Facades\Route;
use Sunrice\Account\Routing\AccountRoutes;

/**
 * My Account's menu: the package's pages and those plugins add with
 * Account::page(). Items are shown in `position` order, while their route
 * exists and their `visible` check (if any) says yes.
 *
 * @api
 */
final class Navigation
{
    /** @var array<string, array{key: string, label: string|Closure(): string, route: string, visible: (Closure(): bool)|null, position: int, icon: string|null}> */
    private array $pages = [];

    /**
     * @param  string|Closure(): string  $label  a closure for a translated label
     * @param  (Closure(): bool)|null  $visible
     */
    public function add(string $key, string|Closure $label, string $route, ?Closure $visible = null, int $position = 100, ?string $icon = null): void
    {
        $this->pages[$key] = ['key' => $key, 'label' => $label, 'route' => $route, 'visible' => $visible, 'position' => $position, 'icon' => $icon];
    }

    public function remove(string $key): void
    {
        unset($this->pages[$key]);
    }

    /** @return list<array{key: string, label: string, url: string, route: string, icon: string|null}> */
    public function items(): array
    {
        $pages = array_filter($this->pages, fn (array $page): bool => Route::has($page['route']) && ($page['visible'] === null || ($page['visible'])()));
        usort($pages, fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return array_map(fn (array $page): array => [
            'key' => $page['key'],
            'label' => $page['label'] instanceof Closure ? ($page['label'])() : $page['label'],
            'url' => AccountRoutes::url($page['route']),
            'route' => $page['route'],
            'icon' => $page['icon'],
        ], $pages);
    }
}
