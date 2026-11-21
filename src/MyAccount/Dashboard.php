<?php

declare(strict_types=1);

namespace Sunrice\Account\MyAccount;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * My Account's first page: its cards (Account::dashboardCard()), and
 * where /account opens (Account::landingUsing()).
 *
 * @api
 */
final class Dashboard
{
    /** @var array<string, array{key: string, view: string, data: (Closure(Authenticatable): array<string, mixed>)|null, position: int}> */
    private array $cards = [];

    /** @var (Closure(Authenticatable): ?string)|null */
    private ?Closure $landing = null;

    /**
     * A card: a view rendered with the account (`$user`) and what `$data` gives.
     *
     * @param  (Closure(Authenticatable): array<string, mixed>)|null  $data  what the view gets beside `$user`
     */
    public function card(string $key, string $view, ?Closure $data = null, int $position = 100): void
    {
        $this->cards[$key] = ['key' => $key, 'view' => $view, 'data' => $data, 'position' => $position];
    }

    public function removeCard(string $key): void
    {
        unset($this->cards[$key]);
    }

    /** @return list<array{key: string, view: string, data: array<string, mixed>}> */
    public function cards(Authenticatable $user): array
    {
        $cards = $this->cards;
        usort($cards, fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return array_map(fn (array $card): array => [
            'key' => $card['key'],
            'view' => $card['view'],
            'data' => ['user' => $user, ...($card['data'] === null ? [] : ($card['data'])($user))],
        ], $cards);
    }

    /** @param  Closure(Authenticatable): ?string  $landing  the URL /account opens, or null for the dashboard */
    public function landingUsing(Closure $landing): void
    {
        $this->landing = $landing;
    }

    public function landing(Authenticatable $user): ?string
    {
        return $this->landing === null ? null : ($this->landing)($user);
    }
}
