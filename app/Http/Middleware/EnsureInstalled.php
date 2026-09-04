<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Redirects to the browser installation wizard until Client Reporter has been
 * installed, and keeps the wizard from being re-run afterwards. A database that
 * is missing or unmigrated is treated as "not installed" so a fresh clone lands
 * on the wizard rather than an error page.
 */
class EnsureInstalled
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Livewire's own endpoints (e.g. the component update route) run in the
        // web group too. They must always pass so the wizard can make component
        // updates without being redirected back to itself, which would look like
        // the page simply reloading on every interaction. Livewire 4 prefixes
        // these routes with a per-app hash (e.g. "livewire-016dcaf9/update"), so
        // match any path whose first segment starts with "livewire".
        if (str_starts_with($request->path(), 'livewire')) {
            return $next($request);
        }

        $installed = $this->isInstalled();
        $onInstaller = $request->is('install', 'install/*');

        if (! $installed && ! $onInstaller) {
            return redirect()->route('install');
        }

        if ($installed && $onInstaller) {
            return redirect()->route('login');
        }

        return $next($request);
    }

    private function isInstalled(): bool
    {
        try {
            return $this->settings->isInstalled();
        } catch (Throwable) {
            return false;
        }
    }
}
