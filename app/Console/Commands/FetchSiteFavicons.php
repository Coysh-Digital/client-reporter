<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Site;
use App\Support\SiteFaviconFetcher;
use Illuminate\Console\Command;

/**
 * Fetches and caches each active site's favicon. Favicons rarely change, so by
 * default this only refreshes ones never fetched or older than a month; the
 * scheduler runs it weekly.
 */
class FetchSiteFavicons extends Command
{
    protected $signature = 'client-reporter:fetch-favicons
        {--force : Refetch every site, ignoring the freshness window}
        {--site= : Limit to a single site id}';

    protected $description = "Fetch and cache active sites' favicons";

    public function handle(SiteFaviconFetcher $fetcher): int
    {
        $sites = Site::query()
            ->where('is_active', true)
            ->when($this->option('site'), fn ($q) => $q->whereKey($this->option('site')))
            ->when(! $this->option('force'), fn ($q) => $q->where(
                fn ($q) => $q->whereNull('favicon_fetched_at')->orWhere('favicon_fetched_at', '<', now()->subDays(30)),
            ))
            ->get();

        $fetched = 0;
        foreach ($sites as $site) {
            if ($fetcher->fetch($site)) {
                $fetched++;
            }
        }

        $this->info("Fetched favicons for {$fetched} of ".$sites->count().' site(s).');

        return self::SUCCESS;
    }
}
