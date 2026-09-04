<?php

declare(strict_types=1);

namespace App\Integrations\Mailchimp;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

/**
 * Collects new signups and audience size for a Mailchimp list. Mailchimp's
 * growth-history API only reports whole calendar months, so a month only
 * counts towards "new leads" when it is fully contained in the requested
 * period (exact for the built-in month/quarter presets; a partial custom
 * range simply won't count a month it doesn't fully cover).
 */
class SummaryCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'summary';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new MailchimpClient((string) $connection->credential('api_key'));
        $listId = (string) $connection->setting('list_id');

        $list = $client->list($listId);
        $months = $client->growthHistory($listId);

        $newLeads = 0;
        foreach ($months as $month) {
            $start = CarbonImmutable::parse($month['month'])->startOfMonth();
            $end = $start->endOfMonth();

            if ($range->contains($start) && $range->contains($end)) {
                $newLeads += $month['optins'] + $month['imports'];
            }
        }

        return CollectorResult::make()
            ->metric('leads.new', $newLeads)
            ->metric('leads.total', (float) ($list['stats']['member_count'] ?? 0))
            ->snapshot(['list_name' => (string) ($list['name'] ?? '')]);
    }
}
