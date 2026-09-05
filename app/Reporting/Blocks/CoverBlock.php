<?php

declare(strict_types=1);

namespace App\Reporting\Blocks;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Support\ReportLang;

class CoverBlock extends BlockType
{
    public function type(): string
    {
        return 'cover';
    }

    public function label(): string
    {
        return ReportLang::get('cover.label');
    }

    public function description(): string
    {
        return 'A branded title page with the client, period and agency identity.';
    }

    public function group(): string
    {
        return 'Structure';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $client = $context->site->client;

        return [
            'client' => $client->name,
            'site' => $context->site->name,
            'period' => $context->range->label(),
            'contact' => $client->contact_name ?? null,
            'prepared_on' => now()->isoFormat('D MMMM YYYY'),
        ];
    }
}
