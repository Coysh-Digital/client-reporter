<?php

declare(strict_types=1);

namespace App\Integrations\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Shared helpers for the Google OAuth integrations (Analytics, Search Console).
 * They use one Google OAuth client (services.google.*) but request different
 * read-only scopes per integration.
 */
class GoogleOAuth
{
    /** integration_key => required OAuth scope. */
    private const SCOPES = [
        'google_analytics' => 'https://www.googleapis.com/auth/analytics.readonly',
        'google_search_console' => 'https://www.googleapis.com/auth/webmasters.readonly',
        'google_ads' => 'https://www.googleapis.com/auth/adwords',
    ];

    public static function isConfigured(): bool
    {
        return ! empty(config('services.google.client_id')) && ! empty(config('services.google.client_secret'));
    }

    public static function scopeFor(string $integrationKey): ?string
    {
        return self::SCOPES[$integrationKey] ?? null;
    }

    public static function supports(string $integrationKey): bool
    {
        return isset(self::SCOPES[$integrationKey]);
    }

    /**
     * Exchange a stored refresh token for a short-lived access token.
     */
    public static function accessToken(string $refreshToken, string $clientId, string $clientSecret): string
    {
        try {
            $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Google. Please try again shortly.');
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token)) {
            throw new IntegrationException('Google declined the connection. It may need to be reconnected.');
        }

        return $token;
    }
}
