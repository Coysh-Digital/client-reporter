<?php

declare(strict_types=1);

namespace App\Integrations\GoogleAnalytics;

use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\DiscoveredConnection;
use App\Integrations\Support\GoogleOAuth;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;
use App\Models\WorkspaceIntegration;
use App\Support\DateRange;
use Illuminate\Support\Facades\Http;

class GoogleAnalyticsIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'google_analytics',
            name: 'Google Analytics 4',
            category: IntegrationCategory::Analytics,
            authMethod: AuthMethod::OAuth,
            description: 'Google Analytics 4: users, sessions, page views, top pages and traffic sources.',
            icon: 'vendor/logos/google_analytics.svg',
            version: '1.0.0',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [
            new ConfigField(
                key: 'property_id',
                label: 'GA4 property ID',
                required: true,
                secret: false,
                help: 'The numeric GA4 property ID (Admin → Property Settings), e.g. 123456789.',
                placeholder: '123456789',
                scope: 'site',
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function workspaceSetupSteps(): array
    {
        return [
            'Click <strong>Connect Google account</strong> and sign in with an account that has access to every GA4 property you want reported.',
            'You’ll return here — click <strong>Find sites</strong> to list every GA4 property on that account.',
            'Match each property to a site below (already guessed by its web stream URL where possible), then create the connections.',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'In Google Analytics, open <strong>Admin → Property Settings</strong> and copy the <strong>Property ID</strong> (a number like 123456789).',
            'Paste it below and press <strong>Save</strong>.',
            'Click <strong>Connect Google account</strong> and sign in with an account that can view this property.',
            'You’ll return here connected — that’s it.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        if (! self::isConfigured()) {
            return VerificationResult::failure('Google OAuth is not configured on this installation yet. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.');
        }

        if (empty($connection->credential('refresh_token'))) {
            return VerificationResult::failure('Connect your Google account to finish setting up this integration.');
        }

        try {
            self::clientFor($connection)->runReport(DateRange::last7Days(), ['activeUsers'], [], 1);
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success('Connected to Google Analytics.');
    }

    public function supportsWorkspaceScope(): bool
    {
        return true;
    }

    public function oauthConnectUrl(WorkspaceIntegration $workspace): string
    {
        return route('integrations.workspace.google.connect', $workspace);
    }

    /**
     * Lists every GA4 property on the account (Admin API accountSummaries),
     * then looks up each property's default web stream URL for auto-matching —
     * a property with no web data stream is skipped, since it can't map to a
     * site's URL.
     *
     * @return array<int, DiscoveredConnection>
     */
    public function discoverConnections(WorkspaceIntegration $workspace): array
    {
        $refreshToken = (string) $workspace->credential('refresh_token');
        if ($refreshToken === '') {
            return [];
        }

        $token = GoogleOAuth::accessToken(
            $refreshToken,
            (string) config('services.google.client_id'),
            (string) config('services.google.client_secret'),
        );

        $response = Http::withToken($token)->timeout(20)->acceptJson()
            ->get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', ['pageSize' => 200]);

        if ($response->failed()) {
            throw new IntegrationException('Google Analytics returned an error (HTTP '.$response->status().') while listing properties.');
        }

        $discovered = [];
        foreach ((array) $response->json('accountSummaries', []) as $account) {
            foreach ((array) ($account['propertySummaries'] ?? []) as $property) {
                $resourceName = (string) ($property['property'] ?? '');
                if ($resourceName === '') {
                    continue;
                }

                $propertyId = str_replace('properties/', '', $resourceName);
                $url = $this->defaultWebStreamUrl($token, $resourceName);

                $discovered[] = new DiscoveredConnection(
                    externalId: $propertyId,
                    label: (string) ($property['displayName'] ?? $propertyId),
                    url: $url,
                    settings: ['property_id' => $propertyId],
                );
            }
        }

        return $discovered;
    }

    private function defaultWebStreamUrl(string $accessToken, string $propertyResourceName): ?string
    {
        $response = Http::withToken($accessToken)->timeout(20)->acceptJson()
            ->get("https://analyticsadmin.googleapis.com/v1beta/{$propertyResourceName}/dataStreams");

        if ($response->failed()) {
            return null;
        }

        foreach ((array) $response->json('dataStreams', []) as $stream) {
            $uri = $stream['webStreamData']['defaultUri'] ?? null;
            if (is_string($uri) && $uri !== '') {
                return $uri;
            }
        }

        return null;
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new GoogleAnalyticsCollector];
    }

    public static function isConfigured(): bool
    {
        return ! empty(config('services.google.client_id')) && ! empty(config('services.google.client_secret'));
    }

    public static function clientFor(SiteIntegration $connection): GoogleAnalyticsClient
    {
        $refreshToken = (string) $connection->credential('refresh_token');
        $propertyId = (string) $connection->setting('property_id');

        if ($refreshToken === '' || $propertyId === '') {
            throw new IntegrationException('This Google Analytics connection is not fully configured yet.');
        }

        return new GoogleAnalyticsClient(
            $refreshToken,
            $propertyId,
            (string) config('services.google.client_id'),
            (string) config('services.google.client_secret'),
        );
    }
}
