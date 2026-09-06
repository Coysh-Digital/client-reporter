<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response headers for client-facing report documents (public share links,
 * the portal and staff previews). The report is a self-contained page with
 * agency-controlled styles but no scripts, so a strict policy costs nothing:
 * no script may run even if a stored value ever slipped through escaping, and
 * a shared link is not cached by intermediaries or indexed by crawlers.
 */
class ReportSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'none'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            'font-src https://fonts.gstatic.com data:',
            "img-src 'self' data: https: http:",
            "form-action 'self'",
            "base-uri 'none'",
            "frame-ancestors 'self'",
            "script-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
