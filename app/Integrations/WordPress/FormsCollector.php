<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use App\Integrations\Connector\SignedConnectorClient;
use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Integrations\Support\IntegrationException;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects website form submissions for the reporting period through the
 * WordPress connector — Gravity Forms and/or Ninja Forms, whichever the site
 * runs. Read-only submission counts, never the submitted data itself.
 */
class FormsCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'forms';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new SignedConnectorClient(
            (string) $connection->setting('base_url'),
            (string) $connection->credential('secret'),
        );

        $result = CollectorResult::make();

        try {
            $data = $client->fetch('forms', [
                'start' => $range->start->toDateString(),
                'end' => $range->end->toDateString(),
            ]);
        } catch (IntegrationException) {
            // An older connector plugin without the /forms route — treat it as
            // "no forms" rather than failing the whole site's collection.
            return $result->snapshot(['active' => false]);
        }

        // No supported forms plugin active on the site — nothing to report.
        if (($data['active'] ?? false) !== true) {
            return $result->snapshot(['active' => false]);
        }

        $forms = array_values((array) ($data['forms'] ?? []));

        return $result
            ->metric('forms.submissions', (int) ($data['total'] ?? 0))
            ->metric('forms.forms', count($forms))
            ->snapshot([
                'active' => true,
                'providers' => array_values((array) ($data['providers'] ?? [])),
                'forms' => $forms,
                'timeseries' => array_values((array) ($data['timeseries'] ?? [])),
            ]);
    }
}
