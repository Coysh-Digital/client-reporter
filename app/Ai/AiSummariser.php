<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Contracts\AiSummaryClient;
use App\Ai\Support\AiException;
use App\Ai\Support\AiMessages;
use App\Models\AiSetting;
use App\Models\Report;
use App\Models\ReportBlock;
use App\Reporting\Blocks\Ai\AiSummaryBlock;
use App\Reporting\BlockTypeRegistry;
use App\Reporting\ReportResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Produces the optional AI summaries for a report. It is the single place the
 * provider is called — from the report generator (filling any empty summaries
 * at freeze time) and from the builder's on-demand preview/regenerate buttons.
 *
 * It is a no-op whenever AI is disabled or unconfigured, so reports are wholly
 * unaffected until a workspace opts in. Every provider call is wrapped so a
 * failure degrades gracefully (the summary is simply omitted) and never aborts
 * report generation. A soft time budget keeps a slow provider from wedging the
 * short-lived scheduled queue worker.
 */
class AiSummariser
{
    /** Stop making calls for a single report once this many seconds have passed. */
    private const TIME_BUDGET_SECONDS = 40;

    private ?CarbonImmutable $deadline = null;

    public function __construct(
        private readonly BlockTypeRegistry $blocks,
        private readonly ReportResolver $resolver,
        private readonly AiSummaryClientFactory $factory,
        private readonly PromptComposer $composer,
    ) {}

    /**
     * Whether summaries can be produced right now.
     */
    public function enabled(): bool
    {
        return AiSetting::current()->isUsable();
    }

    /**
     * Fill any empty AI summaries in an already-resolved block payload, in place,
     * and return it. Existing text (previewed or hand-edited by staff) is kept.
     *
     * @param  array<int, array<string, mixed>>  $data  keyed by block id
     * @return array<int, array<string, mixed>>
     */
    public function augment(Report $report, array $data): array
    {
        $client = $this->clientOrNull();
        if ($client === null) {
            return $data;
        }

        $this->deadline = CarbonImmutable::now()->addSeconds(self::TIME_BUDGET_SECONDS);

        $aggregate = [];

        foreach ($report->blocks as $block) {
            if ($block->is_hidden || ! isset($data[$block->id])) {
                continue;
            }

            $type = $this->blocks->find($block->type);
            if ($type === null) {
                continue;
            }

            /** @var array<string, mixed> $resolved */
            $resolved = $data[$block->id]['data'] ?? [];
            $facts = $type->aiFacts($resolved);

            if ($facts !== [] && $type->type() !== AiSummaryBlock::TYPE) {
                $aggregate[] = ['section' => $block->heading ?: $type->label(), 'facts' => $facts];
            }

            $wantsSummary = $type->supportsAiSummary() && (bool) $block->configValue('ai_summary', false);

            if ($wantsSummary && $facts !== [] && empty($resolved['ai_summary'])) {
                $text = $this->safeComplete($client, $this->composer->summaryFor($type, $facts));
                if ($text !== null) {
                    $data[$block->id]['data']['ai_summary'] = $text;
                }
            }
        }

        foreach ($report->blocks as $block) {
            if ($block->is_hidden || $block->type !== AiSummaryBlock::TYPE || ! isset($data[$block->id])) {
                continue;
            }

            if (! empty($data[$block->id]['data']['ai_summary']) || $aggregate === []) {
                continue;
            }

            $type = $this->blocks->find(AiSummaryBlock::TYPE);
            if ($type === null) {
                continue;
            }

            $text = $this->safeComplete($client, $this->composer->roundupFor($type, $aggregate));
            if ($text !== null) {
                $data[$block->id]['data']['ai_summary'] = $text;
            }
        }

        return $data;
    }

    /**
     * Generate one section's summary on demand (builder button). Returns null
     * when AI is off, the block is not AI-capable, or generation fails.
     */
    public function forBlock(Report $report, ReportBlock $block): ?string
    {
        $client = $this->clientOrNull();
        if ($client === null) {
            return null;
        }

        $type = $this->blocks->find($block->type);
        if ($type === null || ! $type->supportsAiSummary()) {
            return null;
        }

        /** @var array<string, mixed> $resolved */
        $resolved = $this->resolver->resolveBlock($report, $block);
        $facts = $type->aiFacts($resolved);
        if ($facts === []) {
            return null;
        }

        return $this->safeComplete($client, $this->composer->summaryFor($type, $facts));
    }

    /**
     * Generate the report-level roundup on demand (builder button).
     */
    public function roundup(Report $report): ?string
    {
        $client = $this->clientOrNull();
        if ($client === null) {
            return null;
        }

        $roundupType = $this->blocks->find(AiSummaryBlock::TYPE);
        if ($roundupType === null) {
            return null;
        }

        $branding = $this->resolver->branding($report);
        $aggregate = [];

        foreach ($report->blocks as $block) {
            if ($block->is_hidden || $block->type === AiSummaryBlock::TYPE) {
                continue;
            }

            $type = $this->blocks->find($block->type);
            if ($type === null) {
                continue;
            }

            /** @var array<string, mixed> $resolved */
            $resolved = $this->resolver->resolveBlock($report, $block, $branding);
            $facts = $type->aiFacts($resolved);
            if ($facts !== []) {
                $aggregate[] = ['section' => $block->heading ?: $type->label(), 'facts' => $facts];
            }
        }

        if ($aggregate === []) {
            return null;
        }

        return $this->safeComplete($client, $this->composer->roundupFor($roundupType, $aggregate));
    }

    private function clientOrNull(): ?AiSummaryClient
    {
        $setting = AiSetting::current();
        if (! $setting->isUsable()) {
            return null;
        }

        try {
            return $this->factory->make($setting);
        } catch (AiException) {
            return null;
        }
    }

    private function safeComplete(AiSummaryClient $client, AiMessages $messages): ?string
    {
        if ($this->deadline !== null && CarbonImmutable::now()->greaterThan($this->deadline)) {
            return null;
        }

        try {
            return $client->complete($messages);
        } catch (Throwable $e) {
            Log::warning('AI summary generation failed', ['error' => $this->safeMessage($e)]);

            return null;
        }
    }

    private function safeMessage(Throwable $e): string
    {
        if ($e instanceof AiException) {
            return mb_substr($e->getMessage(), 0, 500);
        }

        // Never surface arbitrary exception messages: they can leak internals.
        return 'Unexpected error during AI summary generation ('.class_basename($e).').';
    }
}
