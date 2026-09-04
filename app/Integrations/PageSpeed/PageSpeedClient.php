<?php

declare(strict_types=1);

namespace App\Integrations\PageSpeed;

use App\Integrations\Support\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Google PageSpeed Insights API (v5). Works without a
 * key (rate-limited) or with a Google API key. Returns Lighthouse lab data plus
 * CrUX field data (real-user Core Web Vitals) when the URL has enough traffic.
 */
class PageSpeedClient
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public function __construct(private readonly ?string $apiKey = null) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $url, string $strategy = 'mobile'): array
    {
        $params = [
            'url' => $url,
            'strategy' => $strategy === 'desktop' ? 'desktop' : 'mobile',
            'category' => 'performance',
        ];

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $params['key'] = $this->apiKey;
        }

        try {
            $response = Http::timeout(60)->acceptJson()->get(self::ENDPOINT, $params);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach PageSpeed Insights. Please try again shortly.');
        }

        if ($response->status() === 429) {
            throw new IntegrationException('PageSpeed Insights rate-limited the request. Add a Google API key to raise the limit.');
        }

        if ($response->status() === 400) {
            throw new IntegrationException('PageSpeed Insights could not analyse this URL. Check the site address.');
        }

        if ($response->failed()) {
            throw new IntegrationException('PageSpeed Insights returned an error (HTTP '.$response->status().').');
        }

        return (array) $response->json();
    }
}
