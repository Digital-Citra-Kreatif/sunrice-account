<?php

declare(strict_types=1);

namespace Sunrice\Account\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Honeypot\HoneypotServiceProvider;
use Sunrice\Account\AccountServiceProvider;
use Sunrice\Admin\AdminServiceProvider;
use Sunrice\Cms\CmsServiceProvider;
use Workbench\App\Models\User;
use Workbench\App\Providers\AdminPanelProvider;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            AdminServiceProvider::class,
            CmsServiceProvider::class,
            HoneypotServiceProvider::class,
            AccountServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sunrice-cms.locales', ['id', 'en']);
        $app['config']->set('sunrice-cms.locale_names', ['id' => 'Bahasa Indonesia', 'en' => 'English']);
        // No calls to Have I Been Pwned from the tests.
        $app['config']->set('sunrice-admin.auth.password_uncompromised', false);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fve4Qp/sF3rJf2fDR2lHo4SY9mZhJb+s=');
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
    }

    /**
     * The workbench's users migration runs with the packages' once, by
     * RefreshDatabase. Not loadMigrationsFrom(): it rolls them back after
     * every test, and on MySQL that DDL commits the test's transaction.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->app->make('migrator')->path(__DIR__.'/../workbench/database/migrations');
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(__DIR__.'/../workbench/routes/web.php');
    }

    /** Each request starts with fresh per-request services, as it would in a real request. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app->forgetScopedInstances();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
