<?php

declare(strict_types=1);

namespace App\Integrations\Craft\Blocks;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;

class CraftStatusBlock extends BlockType
{
    public function type(): string
    {
        return 'craft.status';
    }

    public function label(): string
    {
        return 'Craft status';
    }

    public function description(): string
    {
        return 'Craft version, PHP version, environment and queue health.';
    }

    public function group(): string
    {
        return 'Website';
    }

    public function requiresIntegration(): ?string
    {
        return 'craft';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $snapshot = $context->reader->snapshot($context->site, 'craft', 'site', $context->range) ?? [];

        return [
            'craft_version' => $snapshot['craft_version'] ?? null,
            'php_version' => $snapshot['php_version'] ?? null,
            'environment' => $snapshot['environment'] ?? null,
            'queue_pending' => (int) ($snapshot['queue_pending'] ?? 0),
            'queue_failed' => (int) ($snapshot['queue_failed'] ?? 0),
            'licence' => $snapshot['licence'] ?? null,
        ];
    }

    public function icon(): string
    {
        return 'wrench';
    }
}
