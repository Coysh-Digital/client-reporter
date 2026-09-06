<?php

declare(strict_types=1);

namespace App\Integrations\Support;

enum IntegrationCategory: string
{
    case Cms = 'cms';
    case Analytics = 'analytics';
    case Search = 'search';
    case Ecommerce = 'ecommerce';
    case Forms = 'forms';
    case Monitoring = 'monitoring';
    case Performance = 'performance';
    case Downloads = 'downloads';
    case Billing = 'billing';

    public function label(): string
    {
        return match ($this) {
            self::Cms => 'CMS',
            self::Analytics => 'Analytics',
            self::Search => 'Search',
            self::Ecommerce => 'Ecommerce',
            self::Forms => 'Forms & Leads',
            self::Monitoring => 'Monitoring',
            self::Performance => 'Performance',
            self::Downloads => 'Downloads',
            self::Billing => 'Billing',
        };
    }

    /**
     * The metric that best summarises an integration of this category when the
     * integration does not name one itself.
     */
    public function defaultHeadlineMetric(): ?string
    {
        return match ($this) {
            self::Cms => 'cms.updates_total',
            self::Analytics => 'analytics.visitors',
            self::Search => 'search.clicks',
            self::Ecommerce => 'ecommerce.revenue',
            self::Forms => 'leads.new',
            self::Monitoring => 'uptime.percentage',
            self::Performance => 'performance.score',
            self::Downloads => 'downloads.total',
            self::Billing => null,
        };
    }

    /**
     * Display order for the integrations UI.
     *
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return [self::Cms, self::Analytics, self::Search, self::Ecommerce, self::Forms, self::Monitoring, self::Performance, self::Downloads, self::Billing];
    }
}
