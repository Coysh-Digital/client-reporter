<?php

declare(strict_types=1);

namespace App\Integrations\PageSpeed;

use App\Integrations\Support\AbstractHttpClient;

/**
 * Read-only client for the Google PageSpeed Insights API (v5). Works without a
 * key (rate-limited) or with a Google API key. Returns Lighthouse lab data plus
 * CrUX field data (real-user Core Web Vitals) when the URL has enough traffic.
 */
class PageSpeedClient extends AbstractHttpClient
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /** A Lighthouse run is slow; give it a minute and never retry a 5xx (it would double the wait). */
    protected int $timeout = 60;

    protected int $retries = 0;

    public function __construct(private readonly ?string $apiKey = null) {}

    protected function provider(): string
    {
        return 'PageSpeed Insights';
    }

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

        $response = $this->guard($this->get(self::ENDPOINT, $params), [
            429 => 'PageSpeed Insights rate-limited the request. Add a Google API key to raise the limit.',
            400 => 'PageSpeed Insights could not analyse this URL. Check the site address.',
        ]);

        return (array) $response->json();
    }
}
