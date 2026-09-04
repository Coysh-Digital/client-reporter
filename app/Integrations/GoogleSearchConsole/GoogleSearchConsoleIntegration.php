<?php

declare(strict_types=1);

namespace App\Integrations\GoogleSearchConsole;

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

class GoogleSearchConsoleIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'google_search_console',
            name: 'Google Search Console',
            category: IntegrationCategory::Search,
            authMethod: AuthMethod::OAuth,
            description: 'Search performance from Google: clicks, impressions, click-through rate, average position and top queries.',
            icon: 'vendor/logos/google_search_console.svg',
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
                key: 'site_url',
                label: 'Property',
                required: true,
                secret: false,
                help: 'Your exact verified property in Search Console — a URL like https://example.com/ or a domain property like sc-domain:example.com.',
                placeholder: 'https://example.com/',
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
            'Click <strong>Connect Google account</strong> and sign in with an account that has access to every property you want reported.',
            'You’ll return here — click <strong>Find sites</strong> to list every verified property on that account.',
            'Match each property to a site below (already guessed by domain where possible), then create the connections.',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'Copy your property from Search Console — the value in the top-left property picker (a URL like <strong>https://example.com/</strong> or a domain like <strong>sc-domain:example.com</strong>).',
            'Paste it below and press <strong>Save</strong>.',
            'Click <strong>Connect Google account</strong> and sign in with an account that has access to this property.',
            'You’ll return here connected — that’s it.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        if (! GoogleOAuth::isConfigured()) {
            return VerificationResult::failure('Google OAuth is not configured on this installation yet. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.');
        }

        if (empty($connection->credential('refresh_token'))) {
            return VerificationResult::failure('Connect your Google account to finish setting up this integration.');
        }

        try {
            self::clientFor($connection)->query(DateRange::last7Days());
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success('Connected to Google Search Console.');
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
     * @return array<int, DiscoveredConnection>
     */
    public function discoverConnections(WorkspaceIntegration $workspace): array
    {
        $refreshToken = (string) $workspace->credential('refresh_token');
        if ($refreshToken === '') {
            return [];
        }

        $client = new GoogleSearchConsoleClient(
            $refreshToken,
            '',
            (string) config('services.google.client_id'),
            (string) config('services.google.client_secret'),
        );

        return array_map(function (array $entry): DiscoveredConnection {
            $siteUrl = (string) ($entry['siteUrl'] ?? '');
            $domain = str_starts_with($siteUrl, 'sc-domain:') ? substr($siteUrl, strlen('sc-domain:')) : $siteUrl;

            return new DiscoveredConnection(
                externalId: $siteUrl,
                label: $siteUrl !== '' ? $siteUrl : 'Property',
                url: $domain !== '' ? $domain : null,
                settings: ['site_url' => $siteUrl],
            );
        }, $client->sites());
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new SearchAnalyticsCollector];
    }

    public static function clientFor(SiteIntegration $connection): GoogleSearchConsoleClient
    {
        $refreshToken = (string) $connection->credential('refresh_token');
        $siteUrl = (string) $connection->setting('site_url');

        if ($refreshToken === '' || $siteUrl === '') {
            throw new IntegrationException('This Search Console connection is not fully configured yet.');
        }

        return new GoogleSearchConsoleClient(
            $refreshToken,
            $siteUrl,
            (string) config('services.google.client_id'),
            (string) config('services.google.client_secret'),
        );
    }
}
