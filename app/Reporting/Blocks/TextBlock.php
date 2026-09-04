<?php

declare(strict_types=1);

namespace App\Reporting\Blocks;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;

class TextBlock extends BlockType
{
    public function type(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'Text & commentary';
    }

    public function description(): string
    {
        return 'A free-form section for an introduction, commentary or a closing note.';
    }

    public function group(): string
    {
        return 'Content';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        return [];
    }
}
