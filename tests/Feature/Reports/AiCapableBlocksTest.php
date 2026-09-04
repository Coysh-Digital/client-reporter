<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Reporting\Blocks\Ads\AdsSummaryBlock;
use App\Reporting\Blocks\Analytics\AnalyticsSummaryBlock;
use App\Reporting\Blocks\Forms\LeadsSummaryBlock;
use App\Reporting\Blocks\Search\SearchPerformanceBlock;
use App\Reporting\Blocks\Uptime\UptimeSummaryBlock;
use App\Reporting\BlockTypeRegistry;
use Tests\TestCase;

class AiCapableBlocksTest extends TestCase
{
    /**
     * The AI wiring must stay consistent: a block that offers an `ai_summary`
     * toggle must report supportsAiSummary() and ship a default prompt, and one
     * that supports AI must expose the toggle. This guards every block at once,
     * so a new AI-capable section can't ship half-wired.
     */
    public function test_ai_toggle_and_capability_stay_in_step_for_every_block(): void
    {
        foreach (app(BlockTypeRegistry::class)->all() as $type => $block) {
            $hasToggle = collect($block->options())->contains(fn ($opt): bool => $opt->key === 'ai_summary');

            $this->assertSame(
                $block->supportsAiSummary(),
                $hasToggle,
                "Block [{$type}] must expose an ai_summary toggle iff it supports AI summaries.",
            );

            if ($block->supportsAiSummary()) {
                $this->assertNotNull(
                    $block->defaultAiPrompt(),
                    "AI-capable block [{$type}] must provide a default AI prompt.",
                );
            }
        }
    }

    public function test_analytics_summary_facts_carry_the_shown_metrics(): void
    {
        $facts = (new AnalyticsSummaryBlock)->aiFacts([
            'has_data' => true,
            'provider' => 'Plausible',
            'metrics' => [
                ['label' => 'Visitors', 'current' => 1200.0, 'previous' => 1000.0],
                ['label' => 'Page views', 'current' => 3400.0, 'previous' => 3000.0],
            ],
        ]);

        $this->assertSame('Plausible', $facts['provider']);
        $this->assertSame(['current' => 1200.0, 'previous' => 1000.0], $facts['metrics']['Visitors']);
    }

    public function test_search_summary_facts_include_the_top_query(): void
    {
        $facts = (new SearchPerformanceBlock)->aiFacts([
            'has_data' => true,
            'metrics' => [['label' => 'Clicks', 'current' => 500.0, 'previous' => 400.0]],
            'queries' => [['label' => 'best widgets'], ['label' => 'widgets near me']],
        ]);

        $this->assertSame('best widgets', $facts['top_query']);
        $this->assertArrayHasKey('Clicks', $facts['metrics']);
    }

    public function test_data_summary_blocks_return_no_facts_without_data(): void
    {
        foreach ([new AdsSummaryBlock, new LeadsSummaryBlock, new UptimeSummaryBlock] as $block) {
            $this->assertSame([], $block->aiFacts(['has_data' => false]));
        }
    }
}
