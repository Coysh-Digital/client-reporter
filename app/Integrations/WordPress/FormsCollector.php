<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use App\Integrations\Connector\SignedConnectorClient;
use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects form submissions for the reporting period through the WordPress
 * connector — Gravity Forms and/or Ninja Forms, whichever the site has active.
 * Produces a period total and a daily series for the "form responses" chart,
 * plus a per-form breakdown. No-op when neither forms plugin is installed.
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

        $data = $client->fetch('forms', [
            'start' => $range->start->toDateString(),
            'end' => $range->end->toDateString(),
        ]);

        $result = CollectorResult::make();

        // No supported forms plugin active — nothing to report for this site.
        if (($data['active'] ?? false) !== true) {
            return $result->snapshot(['active' => false]);
        }

        $forms = is_array($data['forms'] ?? null) ? $data['forms'] : [];

        return $result
            ->metric('forms.responses', (int) ($data['total'] ?? 0))
            ->metric('forms.forms', count($forms))
            ->snapshot([
                'active' => true,
                'providers' => is_array($data['providers'] ?? null) ? $data['providers'] : [],
                'forms' => $forms,
                'timeseries' => is_array($data['timeseries'] ?? null) ? $data['timeseries'] : [],
            ]);
    }
}
