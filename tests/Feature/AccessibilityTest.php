<?php

declare(strict_types=1);

it('ties each error to its input, and announces them once', function () {
    $html = (string) $this->from('/account/register')->followingRedirects()
        ->post('/account/register', ['name' => '', 'email' => 'not-an-email', 'password' => 'x', 'password_confirmation' => 'y'])->getContent();

    expect($html)->toContain('name="name" aria-invalid="true" aria-describedby="account-error-name"')
        ->toContain('id="account-error-name"')
        ->toContain('aria-describedby="account-error-email"')
        ->and(substr_count($html, 'role="alert"'))->toBe(1);
});

it('marks the overview current on /account', function () {
    $this->actingAs(siteUser(), 'account')->get('/account')->assertOk()
        ->assertSee('href="http://localhost/account/overview" class="account-nav__link is-active"', false)->assertSee('aria-current="page"', false);
});
