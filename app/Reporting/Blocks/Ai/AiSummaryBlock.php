<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Ai;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Support\ReportLang;

/**
 * A report-level "month in review" roundup, written by AI from the figures of
 * every other section. The text is produced at generate time (or via the
 * builder's on-demand button) and stored on the ReportBlock, so this block's
 * own resolve() has nothing to compute — it merely carries the frozen text
 * through, which ReportResolver injects from the persisted column.
 */
class AiSummaryBlock extends BlockType
{
    public const TYPE = 'ai.summary';

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return ReportLang::get('ai_summary.heading');
    }

    public function description(): string
    {
        return 'An AI-written roundup of the whole report, drawn from every section\'s figures. Requires AI configured in Settings.';
    }

    public function group(): string
    {
        return 'Content';
    }

    public function defaultAiPrompt(): ?string
    {
        return 'Write a short "month in review" roundup for a non-technical client that '
            .'summarises the whole report in three to four sentences, drawing on the figures '
            .'for each section provided. Lead with the headline story of the month. Use only '
            .'the figures provided; never invent numbers.';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        return ['ai_summary' => null];
    }
}
