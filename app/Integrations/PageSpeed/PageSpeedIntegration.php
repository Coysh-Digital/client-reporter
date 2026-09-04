<?php

declare(strict_types=1);

namespace App\Integrations\PageSpeed;

use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;

class PageSpeedIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'pagespeed',
            name: 'PageSpeed',
            category: IntegrationCategory::Performance,
            authMethod: AuthMethod::ApiKey,
            description: 'Core Web Vitals (LCP, INP, CLS) and a performance score from Google PageSpeed Insights.',
            icon: 'vendor/logos/pagespeed.svg',
            version: '1.0.0',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [
            new ConfigField(key: 'api_key', label: 'Google API key', required: false, secret: true, help: 'Optional but recommended — a key with the PageSpeed Insights API enabled avoids rate limits. Leave blank to try without one.'),
            ConfigField::select('strategy', 'Measure', ['mobile' => 'Mobile experience', 'desktop' => 'Desktop experience'], false),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'PageSpeed Insights works out of the box — you can connect with <strong>no key</strong> to start.',
            '<strong>Recommended:</strong> in Google Cloud Console, enable the <strong>PageSpeed Insights API</strong> and create an API key to avoid rate limits.',
            'Choose whether to measure the <strong>Mobile</strong> or <strong>Desktop</strong> experience.',
            'Press <strong>Connect &amp; verify</strong> — we measure this site’s address automatically.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            (new PageSpeedClient((string) $connection->credential('api_key') ?: null))
                ->analyze($connection->site->url, (string) ($connection->setting('strategy') ?: 'mobile'));
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success('Connected to PageSpeed Insights.');
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new PageSpeedCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        return [];
    }
}
