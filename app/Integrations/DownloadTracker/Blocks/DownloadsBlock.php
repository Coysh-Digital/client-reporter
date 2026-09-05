<?php

declare(strict_types=1);

namespace App\Integrations\DownloadTracker\Blocks;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\Format;

/**
 * File downloads from Download Tracker: the period total and distinct files as
 * headline tiles, a daily downloads trend, and the most-downloaded files.
 */
class DownloadsBlock extends BlockType
{
    public function type(): string
    {
        return 'downloads.summary';
    }

    public function label(): string
    {
        return 'Downloads';
    }

    public function description(): string
    {
        return 'File download totals, a daily trend and the most-downloaded files.';
    }

    public function group(): string
    {
        return 'Downloads';
    }

    public function icon(): string
    {
        return 'download';
    }

    public function requiresIntegration(): ?string
    {
        return 'download_tracker';
    }

    public function options(): array
    {
        return [
            BlockOption::toggle('compare', 'Compare to previous period', true),
            BlockOption::toggle('show_chart', 'Show daily downloads chart', true),
            BlockOption::toggle('show_files', 'Show top files', true),
            BlockOption::number('files_limit', 'Top files to show', 8, 3, 25),
            BlockOption::toggle('ai_summary', 'AI summary', false, 'Add an AI-written paragraph summarising this section (requires AI configured in Settings).'),
        ];
    }

    public function supportsAiSummary(): bool
    {
        return true;
    }

    public function defaultAiPrompt(): ?string
    {
        return 'Summarise this period\'s file downloads for a non-technical client in two to '
            .'three sentences. Cover the total downloads and how it moved versus the prior '
            .'period, and the most popular file. Use only the figures provided.';
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>
     */
    public function aiFacts(array $resolved): array
    {
        if (! ($resolved['has_data'] ?? false)) {
            return [];
        }

        $metrics = [];
        foreach ($resolved['tiles'] ?? [] as $tile) {
            $metrics[$tile['label']] = ['current' => $tile['current'], 'previous' => $tile['previous']];
        }

        return array_filter([
            'metrics' => $metrics,
            'top_file' => $resolved['top_files'][0]['label'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);

        $current = $context->reader->metrics($context->site, 'download_tracker', $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metrics($context->site, 'download_tracker', $context->comparison)
            : [];

        $snapshot = $context->reader->snapshot($context->site, 'download_tracker', 'downloads', $context->range) ?? [];

        $tile = fn (string $key, string $label): array => [
            'label' => $label,
            'fmt' => 'number',
            'goodUp' => true,
            'current' => $current[$key]['value'] ?? null,
            'previous' => $previous[$key]['value'] ?? null,
        ];

        $files = [];
        if ((bool) $context->block->configValue('show_files', true)) {
            $limit = (int) $context->block->configValue('files_limit', 8);
            $files = array_slice($snapshot['top_files'] ?? [], 0, $limit);
        }

        return [
            'has_data' => $current !== [],
            'tiles' => [
                $tile('downloads.total', 'Downloads'),
                $tile('downloads.files', 'Files downloaded'),
            ],
            'top_files' => $files,
            'timeseries' => (bool) $context->block->configValue('show_chart', true) ? ($snapshot['timeseries'] ?? []) : [],
            'insight' => $this->insight($current['downloads.total']['value'] ?? null, $previous['downloads.total']['value'] ?? null),
        ];
    }

    private function insight(?float $current, ?float $previous): ?string
    {
        if ($current === null) {
            return null;
        }

        $count = (int) $current;
        $sentence = Format::number($current).' '.($count === 1 ? 'file download' : 'file downloads').' this period';

        $change = Format::change($current, $previous);
        if ($change['percent'] !== null && $change['direction'] !== 'flat') {
            $sentence .= ', '.$change['direction'].' '.Format::number(abs($change['percent']), 1).'% on the previous period';
        }

        return $sentence.'.';
    }
}
