<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Support\AiMessages;
use App\Reporting\Contracts\BlockType;
use App\Support\Settings;

/**
 * Builds the prompts sent to the AI provider. The system prompt fixes the
 * house rules (factual, figures-only, one plain paragraph) and appends the
 * agency's editable global tone. The user prompt combines the per-component
 * instruction (a stored override, else the block's default) with the block's
 * whitelisted figures serialised as JSON.
 */
class PromptComposer
{
    public function __construct(private readonly Settings $settings) {}

    public function systemPrompt(): string
    {
        $base = 'You write concise, factual summaries for a white-labelled website care report. '
            .'Use only the figures provided — never invent, estimate or infer numbers that are not present. '
            .'The report covers the reporting period stated in the data; describe that timeframe as given '
            .'(it may be a week, month, quarter or a custom range) and never assume it is a calendar month — '
            .'do not say "this month" unless the period is a single calendar month. '
            .'Write a single short paragraph of plain prose for a non-technical client: no markdown, '
            .'no headings, no bullet points, and no preamble such as "Here is a summary".';

        $tone = trim((string) $this->settings->get('ai.tone', ''));

        return $tone !== '' ? $base."\n\nTone and style to follow: ".$tone : $base;
    }

    /**
     * Prompt for a single section's AI summary. $period is the report's
     * reporting-period label, so the model describes the right timeframe.
     *
     * @param  array<string, mixed>  $facts
     */
    public function summaryFor(BlockType $type, array $facts, ?string $period = null): AiMessages
    {
        return new AiMessages(
            $this->systemPrompt(),
            $this->body($this->instructionFor($type), $facts, $period),
        );
    }

    /**
     * Prompt for the report-level roundup.
     *
     * @param  array<int, array{section: string, facts: array<string, mixed>}>  $sections
     */
    public function roundupFor(BlockType $type, array $sections, ?string $period = null): AiMessages
    {
        return new AiMessages(
            $this->systemPrompt(),
            $this->body($this->instructionFor($type), ['sections' => $sections], $period),
        );
    }

    /**
     * The stored per-component override, or the block's built-in default.
     */
    private function instructionFor(BlockType $type): string
    {
        $override = trim((string) $this->settings->get('ai.prompt.'.$type->type(), ''));

        return $override !== '' ? $override : (string) $type->defaultAiPrompt();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function body(string $instruction, array $data, ?string $period = null): string
    {
        $preamble = $period !== null && trim($period) !== ''
            ? 'Reporting period: '.trim($period).".\n\n"
            : '';

        return $preamble.$instruction."\n\nData (JSON):\n".json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
    }
}
