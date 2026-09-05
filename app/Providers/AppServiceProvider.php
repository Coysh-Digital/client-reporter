<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Importers\MainWpImporter;
use App\Importers\ManageWpImporter;
use App\Importers\SiteImporterRegistry;
use App\Importers\WpMgrImporter;
use App\Integrations\ExtensionLoader;
use App\Integrations\IntegrationRegistry;
use App\Models\User;
use App\Reporting\BlockTypeRegistry;
use App\Support\Http\DnsResolver;
use App\Support\Http\SystemDnsResolver;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class, fn ($app): Settings => new Settings($app['cache.store']));

        // Outbound URL checks resolve hostnames through this seam; tests swap
        // in a fixed map so nothing touches real DNS.
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);

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
        $this->defineRateLimiters();
        $this->hardenDefaults();
    }

    /**
     * Named limiters for the endpoints that accept unauthenticated or
     * credential-bearing input. Login and the 2FA challenge keep their own
     * per-account limiters in the components.
     */
    private function defineRateLimiters(): void
    {
        RateLimiter::for('install', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->ip()));

        // Per share link *and* per address, so one visitor guessing a password
        // cannot lock out the client the link was sent to.
        RateLimiter::for('share-unlock', fn (Request $request): Limit => Limit::perMinute(10)
            ->by(sha1((string) $request->route('token')).'|'.$request->ip()));

        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    /**
     * Framework-wide defaults that make the safe thing the default thing.
     */
    private function hardenDefaults(): void
    {
        // Length over complexity: a 12-character minimum everywhere a password
        // is set (installer, user management, reset). Kept free of the
        // "uncompromised" check so an offline install can still set passwords.
        Password::defaults(fn (): Password => Password::min(12));

        // Surface N+1 lazy loads and silently dropped (non-fillable) attributes
        // during development and testing, where they are bugs rather than
        // surprises. Missing-attribute strictness is deliberately left off: a
        // freshly created model legitimately lacks columns it never set.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // A user deactivated mid-session must be signed out on their next
        // Livewire action, not only on their next full page load.
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);
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
