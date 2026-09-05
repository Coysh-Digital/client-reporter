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
     * Display order for the integrations UI.
     *
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return [self::Cms, self::Analytics, self::Search, self::Ecommerce, self::Forms, self::Monitoring, self::Performance, self::Downloads, self::Billing];
    }
}
