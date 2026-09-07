<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BackgroundTaskStatus;
use App\Models\BackgroundTask;
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

    public function displayName(): string
    {
        return 'Fetch favicon: '.$this->site->name;
    }

    public function handle(SiteFaviconFetcher $fetcher): void
    {
        $task = BackgroundTask::record(
            BackgroundTask::KIND_FAVICON,
            BackgroundTask::KIND_FAVICON.':'.$this->site->id,
            BackgroundTaskStatus::Running,
            'Fetching favicon',
            $this->site->name,
            $this->site,
        )->markRunning();

        $fetcher->fetch($this->site);

        $task->succeed();
    }
}
