<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Importers\MainWpImporter;
use App\Importers\ManageWpImporter;
use App\Importers\SiteImporterRegistry;
use App\Importers\WpMgrImporter;
use App\Integrations\ExtensionLoader;
use App\Integrations\IntegrationRegistry;
use App\Models\User;
use App\Reporting\BlockTypeRegistry;
use App\Support\Settings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class, fn ($app): Settings => new Settings($app['cache.store']));

        // Autoload custom integrations dropped into the (git-ignored) extensions/
        // directory, so they survive updates without a composer require.
        ExtensionLoader::registerAutoloaders();

        $this->app->singleton(IntegrationRegistry::class, function (): IntegrationRegistry {
            $classes = array_values(array_unique(array_merge(
                (array) config('client-reporter.integrations', []),
                IntegrationRegistry::discoverFromComposer(),
                ExtensionLoader::integrationClasses(),
                ExtensionLoader::localIntegrationClasses(),
            )));

            return new IntegrationRegistry($classes);
        });

        $this->app->singleton(BlockTypeRegistry::class, fn ($app): BlockTypeRegistry => new BlockTypeRegistry(
            (array) config('client-reporter.report_blocks', []),
            $app->make(IntegrationRegistry::class),
        ));

        $this->app->singleton(SiteImporterRegistry::class, fn (): SiteImporterRegistry => new SiteImporterRegistry([
            new WpMgrImporter,
            new MainWpImporter,
            new ManageWpImporter,
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->defineGates();
    }

    /**
     * Authorisation gates. Roles are coarse (see {@see UserRole}); gates express
     * the capability each staff role has, so new roles can be slotted into the
     * hierarchy without touching call sites. Administrators pass every gate via
     * the Gate::before short-circuit.
     */
    private function defineGates(): void
    {
        Gate::before(fn (User $user) => $user->isAdministrator() ? true : null);

        // Any active staff member may reach the agency admin interface.
        Gate::define('access-admin', fn (User $user): bool => $user->isStaff());

        // Client-portal users reach only the portal.
        Gate::define('access-portal', fn (User $user): bool => $user->isClient() && $user->client_id !== null);

        // Managers and above manage the agency's working data.
        foreach (['manage-clients', 'manage-sites', 'manage-integrations', 'manage-reports'] as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->hasAtLeastRole(UserRole::Manager));
        }

        // Administrator-only capabilities (also allowed by Gate::before, but
        // stated explicitly so intent is clear and testable).
        foreach (['manage-users', 'manage-branding', 'manage-settings'] as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->hasAtLeastRole(UserRole::Administrator));
        }
    }
}
