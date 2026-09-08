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

    /** The Lighthouse categories to score, in the API's own enum spelling. */
    private const CATEGORIES = ['PERFORMANCE', 'ACCESSIBILITY', 'BEST_PRACTICES', 'SEO'];

    /** A Lighthouse run is slow, so allow a full minute per attempt. */
    protected int $timeout = 60;

    /**
     * PageSpeed's Lighthouse backend returns transient 5xx errors fairly often.
     * One retry after a short pause absorbs most of them; it only costs a second
     * slow call when the first genuinely fails, and this runs in the background.
     * (Only 5xx/connection errors are retried — never a 4xx or a 429.)
     */
    protected int $retries = 1;

    protected int $retryDelayMs = 1500;

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
        ];

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $params['key'] = $this->apiKey;
        }

        // The Lighthouse category scores each need their own `category` param.
        // Laravel would serialise an array as `category[0]=…`, which the API
        // ignores, so the repeated params are appended to the query by hand.
        $query = http_build_query($params);
        foreach (self::CATEGORIES as $category) {
            $query .= '&category='.$category;
        }

        $response = $this->guard($this->get(self::ENDPOINT.'?'.$query), [
            429 => 'PageSpeed Insights rate-limited the request. Add a Google API key to raise the limit.',
            400 => 'PageSpeed Insights could not analyse this URL. Check the site address.',
        ]);

        return (array) $response->json();
    }
}
