<?php

declare(strict_types=1);

namespace App\Integrations\GoogleAds;

use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\GoogleOAuth;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

class GoogleAdsIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'google_ads',
            name: 'Google Ads',
            category: IntegrationCategory::Analytics,
            authMethod: AuthMethod::OAuth,
            description: 'Spend, clicks, impressions and conversions from a Google Ads account.',
            icon: 'vendor/logos/google_ads.svg',
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
                key: 'customer_id',
                label: 'Google Ads customer ID',
                required: true,
                secret: false,
                help: 'The account ID shown top-right in Google Ads, e.g. 123-456-7890.',
                placeholder: '123-456-7890',
                scope: 'site',
            ),
            new ConfigField(
                key: 'developer_token',
                label: 'Developer token',
                required: true,
                secret: true,
                help: 'From Google Ads → Tools & Settings → API Center. One token per manager account, reused across every connection.',
                scope: 'account',
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'In Google Ads, copy the account\'s <strong>Customer ID</strong> (top-right, e.g. 123-456-7890) and paste it below.',
            'Under <strong>Tools &amp; Settings → API Center</strong>, copy your <strong>Developer token</strong> and paste it below.',
            'Click <strong>Connect Google account</strong> and sign in with an account that can access this Google Ads account.',
            'You\'ll return here connected — that\'s it.',
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
            self::clientFor($connection)->summary(DateRange::last7Days());
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success('Connected to Google Ads.');
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new GoogleAdsCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        return [];
    }

    public static function clientFor(SiteIntegration $connection): GoogleAdsClient
    {
        $refreshToken = (string) $connection->credential('refresh_token');
        $customerId = (string) $connection->setting('customer_id');
        $developerToken = (string) $connection->credential('developer_token');

        if ($refreshToken === '' || $customerId === '' || $developerToken === '') {
            throw new IntegrationException('This Google Ads connection is not fully configured yet.');
        }

        return new GoogleAdsClient(
            $refreshToken,
            $customerId,
            $developerToken,
            (string) config('services.google.client_id'),
            (string) config('services.google.client_secret'),
        );
    }
}
