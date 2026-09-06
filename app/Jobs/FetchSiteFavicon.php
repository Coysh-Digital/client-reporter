<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Site;
use App\Support\SiteFaviconFetcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fetches and caches one site's favicon. Dispatched per site by
 * `client-reporter:fetch-favicons`; the fetcher itself is best-effort and
 * never throws, so a single attempt is enough.
 */
class FetchSiteFavicon implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public Site $site) {}

    public function uniqueId(): string
    {
        return (string) $this->site->id;
    }

    public function handle(SiteFaviconFetcher $fetcher): void
    {
        $fetcher->fetch($this->site);
    }
}
