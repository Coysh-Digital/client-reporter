<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Binds an OAuth authorisation round-trip to the browser session that started
 * it. The `state` parameter sent to the provider is a single-use random nonce;
 * what it refers to (which connection to store the token on) stays server-side
 * in the session, so a captured or replayed callback URL cannot attach an
 * attacker's account to the agency's connection.
 */
final class OAuthState
{
    private const SESSION_KEY = 'oauth.state';

    private const TTL_MINUTES = 10;

    /**
     * Start a flow: remember the target and return the nonce to send as `state`.
     */
    public static function issue(string $provider, string $target): string
    {
        $nonce = Str::random(40);

        session()->put(self::SESSION_KEY, [
            'provider' => $provider,
            'nonce' => $nonce,
            'target' => $target,
            'issued_at' => now()->timestamp,
        ]);

        return $nonce;
    }

    /**
     * Finish a flow: verify the incoming `state` against the session and return
     * the stored target. Aborts with 403 when the state is missing, foreign,
     * reused or too old.
     */
    public static function consume(Request $request, string $provider): string
    {
        $stored = session()->pull(self::SESSION_KEY);
        $presented = (string) $request->query('state', '');

        $valid = is_array($stored)
            && ($stored['provider'] ?? null) === $provider
            && is_string($stored['nonce'] ?? null)
            && $presented !== ''
            && hash_equals($stored['nonce'], $presented)
            && is_int($stored['issued_at'] ?? null)
            && now()->timestamp - $stored['issued_at'] <= self::TTL_MINUTES * 60;

        if (! $valid) {
            throw new HttpException(403, 'Invalid or expired OAuth state. Please start the connection again.');
        }

        return (string) $stored['target'];
    }
}
