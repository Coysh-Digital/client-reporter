<?php

declare(strict_types=1);

namespace App\Reporting\Blocks;

use App\Reporting\BlockTypeRegistry;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Support\ReportLang;

/**
 * A jump-to list of every other section in the report, with an icon per
 * section — the report's own table of contents. Reads the report's sibling
 * blocks directly (bypassing the metric layer entirely, like
 * {@see WebsiteOverviewBlock}), since a table of contents describes the
 * report's own structure, not collected data.
 */
class ContentsBlock extends BlockType
{
    /** Structural blocks that never appear as an entry in their own contents list. */
    private const EXCLUDED_TYPES = ['cover', 'contents', 'closing'];

    public function type(): string
    {
        return 'contents';
    }

    public function label(): string
    {
        return ReportLang::get('contents.heading');
    }

    public function description(): string
    {
        return 'A jump-to list of every section in the report, with an icon per section.';
    }

    public function group(): string
    {
        return 'General';
    }

    public function supportsCommentary(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $registry = app(BlockTypeRegistry::class);
        $siblings = $context->block->report
            ->blocks()
            ->orderBy('position')
            ->get();

        $items = [];
        foreach ($siblings as $sibling) {
            if ($sibling->is_hidden || in_array($sibling->type, self::EXCLUDED_TYPES, true)) {
                continue;
            }

            $type = $registry->find($sibling->type);
            if ($type === null) {
                continue;
            }

            $items[] = [
                'anchor' => 'block-'.$sibling->id,
                'heading' => $sibling->heading ?: $type->label(),
                'icon' => $type->icon(),
            ];
        }

        return ['items' => $items];
    }
}
