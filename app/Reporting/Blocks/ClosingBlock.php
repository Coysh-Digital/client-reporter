<?php

declare(strict_types=1);

namespace App\Reporting\Blocks;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;

class ClosingBlock extends BlockType
{
    public function type(): string
    {
        return 'closing';
    }

    public function label(): string
    {
        return 'Closing message';
    }

    public function description(): string
    {
        return 'A sign-off with your agency contact details.';
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
        return [
            'email' => $context->branding->email,
            'phone' => $context->branding->phone,
            'website' => $context->branding->website,
        ];
    }
}
