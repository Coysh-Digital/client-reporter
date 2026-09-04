<?php

declare(strict_types=1);

namespace App\Integrations\GoogleAds;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

class GoogleAdsCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'summary';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $summary = GoogleAdsIntegration::clientFor($connection)->summary($range);

        return CollectorResult::make()
            ->metric('ads.spend', $summary['spend'], $summary['currency'])
            ->metric('ads.clicks', $summary['clicks'])
            ->metric('ads.impressions', $summary['impressions'])
            ->metric('ads.conversions', $summary['conversions'])
            ->snapshot(['currency' => $summary['currency']]);
    }
}
