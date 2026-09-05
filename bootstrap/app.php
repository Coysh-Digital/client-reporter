<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ReportSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'ability' => CheckForAnyAbility::class,
            'abilities' => CheckAbilities::class,
            'report-headers' => ReportSecurityHeaders::class,
        ]);

        // Behind a reverse proxy or CDN the client address and scheme arrive in
        // forwarded headers. TRUSTED_PROXIES lists the proxies to believe ("*"
        // for a CDN such as Cloudflare); unset means none, so an unproxied
        // install cannot be fooled by a spoofed X-Forwarded-For.
        $proxies = trim((string) env('TRUSTED_PROXIES', ''));
        if ($proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Absolute URLs (password-reset links, share links) are built from the
        // Host header; only accept the host APP_URL is configured for.
        $middleware->trustHosts(at: fn (): array => array_filter([parse_url((string) config('app.url'), PHP_URL_HOST)]), subdomains: false);

        // Gate the whole app behind the installation wizard until installed.
        $middleware->web(append: [
            EnsureInstalled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
