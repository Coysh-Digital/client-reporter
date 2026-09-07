<?php

declare(strict_types=1);

namespace App\Integrations\EmailOctopus;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CampaignMetrics;
use App\Integrations\Support\CollectorResult;
use App\Integrations\Support\IntegrationException;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects new subscribers and audience size for an EmailOctopus list. New
 * subscribers are counted from the contacts endpoint filtered on the requested
 * period, so any date range is honoured exactly.
 */
class SummaryCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'summary';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new EmailOctopusClient((string) $connection->credential('api_key'));
        $listId = (string) $connection->setting('list_id');

        $list = $client->list($listId);
        $newLeads = $client->newSubscribers($listId, $range);
        $total = EmailOctopusClient::countsFor($list)['subscribed'];

        // Campaign performance is supplementary to the leads figures, so a
        // failure to read it never breaks the core collection.
        try {
            $campaigns = $client->campaigns($range);
        } catch (IntegrationException) {
            $campaigns = [];
        }

        $result = CollectorResult::make()
            ->metric('leads.new', $newLeads)
            ->metric('leads.total', (float) $total);

        CampaignMetrics::apply($result, $campaigns);

        return $result->snapshot([
            'list_name' => (string) ($list['name'] ?? ''),
            'campaigns' => $campaigns,
        ]);
    }
}
