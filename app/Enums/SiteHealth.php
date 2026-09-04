<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A site's derived operational health, rolled up from its integration
 * connection states and current-period signals (uptime, CMS updates). There is
 * no stored health column — this is computed by SiteHealthResolver.
 */
enum SiteHealth: string
{
    case Healthy = 'healthy';
    case NeedsAttention = 'needs_attention';
    case Down = 'down';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::NeedsAttention => 'Needs attention',
            self::Down => 'Down',
        };
    }

    /**
     * Maps to the <x-badge> / <x-status-dot> variant palette.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Healthy => 'ok',
            self::NeedsAttention => 'warn',
            self::Down => 'danger',
        };
    }

    /**
     * Higher = worse; used to order sites by attention needed.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Down => 2,
            self::NeedsAttention => 1,
            self::Healthy => 0,
        };
    }
}
