<?php

declare(strict_types=1);

namespace App\Reporting\Support;

use App\Models\ReportBlock;
use App\Models\Site;
use App\Reporting\MetricReader;
use App\Support\Branding\ResolvedBranding;
use App\Support\DateRange;

/**
 * Everything a block needs to resolve its data for a report: the site, the
 * report period and its comparison period, a reader over stored metrics, the
 * resolved branding, and the block instance (for its config, heading and
 * commentary).
 */
readonly class BlockContext
{
    public function __construct(
        public Site $site,
        public ReportBlock $block,
        public DateRange $range,
        public ?DateRange $comparison,
        public MetricReader $reader,
        public ResolvedBranding $branding,
    ) {}
}
