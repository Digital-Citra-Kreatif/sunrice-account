<?php

declare(strict_types=1);

use Sunrice\Account\Facades\Account;
use Workbench\App\Models\User;

beforeEach(function () {
    Account::registrationFields(fn () => [
        ['key' => 'shop_company', 'label' => 'Company', 'rules' => ['required', 'string', 'max:80']],
        ['key' => 'shop_newsletter', 'label' => fn () => 'Weekly letter', 'type' => 'checkbox'],
    ]);
    Account::profileFields(fn () => [
        ['key' => 'shop_company', 'label' => 'Company', 'rules' => ['nullable', 'string', 'max:80']],
        ['key' => 'shop_size', 'label' => 'Size', 'type' => 'select', 'options' => ['s' => 'Small', 'l' => 'Large']],
    ]);
});

it('adds plugins\' fields to registration, validated and kept in the profile', function () {
    Account::settings()->saveSection('general', ['verify_email' => false]);
    $this->get('/account/register')->assertSee('name="fields[shop_company]"', false)->assertSee('Weekly letter');
    $input = ['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'Correct-Horse-Battery-9', 'password_confirmation' => 'Correct-Horse-Battery-9'];

    $this->post('/account/register', $input)->assertSessionHasErrors('fields.shop_company');
    $this->post('/account/register', [...$input, 'fields' => ['shop_company' => 'Kopi Ana']])->assertRedirect('/account');

    $profile = Account::profile(User::query()->where('email', 'budi@example.com')->firstOrFail());
    expect($profile->metaValue('shop_company'))->toBe('Kopi Ana')->and($profile->metaValue('shop_newsletter'))->toBeFalse();
});

it('adds plugins\' fields to the profile page', function () {
    $user = siteUser();
    $this->actingAs($user, 'account');
    $this->get('/account/profile')->assertSee('name="fields[shop_size]"', false)->assertSee('Large');

    $this->put('/account/profile', ['name' => 'Ana', 'email' => 'ana@example.com', 'fields' => ['shop_size' => 'xl']])->assertSessionHasErrors('fields.shop_size');
    $this->put('/account/profile', ['name' => 'Ana', 'email' => 'ana@example.com', 'fields' => ['shop_size' => 'l', 'shop_company' => 'Kopi']])->assertSessionHasNoErrors();

    expect(Account::profile($user)->metaValue('shop_size'))->toBe('l')
        ->and(Account::profile($user)->metaValue('shop_company'))->toBe('Kopi');
    $this->get('/account/profile')->assertSee('value="Kopi"', false);
});

it('wants a key like shop_company and a known type', function () {
    Account::profileFields(fn () => [['key' => 'Bad Key']]);

    $this->actingAs(siteUser(), 'account')->get('/account/profile')->assertServerError();
});
