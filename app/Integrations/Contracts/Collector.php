<?php

declare(strict_types=1);

namespace App\Integrations\Contracts;

use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * A unit of scheduled data collection for an integration. Collectors are pure:
 * they fetch from the external service and return a CollectorResult. Persistence,
 * run tracking and error handling are done by the CollectorRunner.
 */
interface Collector
{
    /**
     * Stable key, unique within the integration (e.g. "monitors", "summary").
     */
    public function key(): string;

    /**
     * Collect data for the given period.
     */
    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult;

    /**
     * How often this collector should run, in minutes.
     */
    public function intervalMinutes(): int;
}
