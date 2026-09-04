<?php

declare(strict_types=1);

namespace App\Integrations\WordPress\Blocks;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;

class CmsStatusBlock extends BlockType
{
    public function type(): string
    {
        return 'cms.status';
    }

    public function label(): string
    {
        return 'CMS status';
    }

    public function description(): string
    {
        return 'WordPress version, PHP version, active theme and Site Health.';
    }

    public function group(): string
    {
        return 'Website';
    }

    public function requiresIntegration(): ?string
    {
        return 'wordpress';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $snapshot = $context->reader->snapshot($context->site, 'wordpress', 'site', $context->range) ?? [];

        return [
            'wordpress_version' => $snapshot['wordpress_version'] ?? null,
            'php_version' => $snapshot['php_version'] ?? null,
            'active_theme' => $snapshot['active_theme'] ?? null,
            'site_health' => $snapshot['site_health'] ?? null,
            'users' => $context->reader->metricValue($context->site, 'wordpress', 'cms.users', $context->range),
        ];
    }

    public function icon(): string
    {
        return 'wrench';
    }
}
