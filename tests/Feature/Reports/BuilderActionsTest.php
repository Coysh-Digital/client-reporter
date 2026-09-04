<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Livewire\Reports\Builder;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BuilderActionsTest extends TestCase
{
    use RefreshDatabase;

    private function report(): Report
    {
        return Report::factory()->for(Site::factory())->create();
    }

    public function test_duplicate_clones_a_section_directly_beneath_it(): void
    {
        $report = $this->report();
        $a = $report->blocks()->create(['type' => 'text', 'position' => 0, 'heading' => 'Intro', 'commentary' => 'Hello']);
        $report->blocks()->create(['type' => 'closing', 'position' => 1, 'heading' => 'Bye']);

        Livewire::actingAs(User::factory()->manager()->create())
            ->test(Builder::class, ['report' => $report])
            ->call('duplicateBlock', $a->id);

        $ordered = $report->blocks()->orderBy('position')->get();
        $this->assertCount(3, $ordered);
        // The clone sits immediately after the original and copies its content.
        $this->assertSame('text', $ordered[1]->type);
        $this->assertSame('Hello', $ordered[1]->commentary);
        $this->assertSame('closing', $ordered[2]->type);
    }

    public function test_move_block_reorders_up_and_down(): void
    {
        $report = $this->report();
        $a = $report->blocks()->create(['type' => 'text', 'position' => 0, 'heading' => 'A']);
        $b = $report->blocks()->create(['type' => 'closing', 'position' => 1, 'heading' => 'B']);

        Livewire::actingAs(User::factory()->manager()->create())
            ->test(Builder::class, ['report' => $report])
            ->call('moveBlock', $b->id, 'up');

        $order = $report->blocks()->orderBy('position')->pluck('id')->all();
        $this->assertSame([$b->id, $a->id], $order);
    }

    public function test_toggle_hidden_flips_visibility(): void
    {
        $report = $this->report();
        $block = $report->blocks()->create(['type' => 'text', 'position' => 0, 'heading' => 'A']);

        Livewire::actingAs(User::factory()->manager()->create())
            ->test(Builder::class, ['report' => $report])
            ->call('toggleHidden', $block->id)
            ->assertSet('edits.'.$block->id.'.is_hidden', true);

        $this->assertTrue($block->refresh()->is_hidden);
    }

    public function test_apply_template_seeds_an_empty_report(): void
    {
        $report = $this->report();
        $template = ReportTemplate::query()->create([
            'name' => 'Standard',
            'blocks' => [
                ['type' => 'cover', 'heading' => 'Cover'],
                ['type' => 'text', 'heading' => 'Intro'],
            ],
        ]);

        Livewire::actingAs(User::factory()->manager()->create())
            ->test(Builder::class, ['report' => $report])
            ->call('applyTemplate', $template->id);

        $this->assertSame(['cover', 'text'], $report->blocks()->orderBy('position')->pluck('type')->all());
    }

    public function test_apply_template_is_ignored_when_the_report_already_has_sections(): void
    {
        $report = $this->report();
        $report->blocks()->create(['type' => 'text', 'position' => 0, 'heading' => 'Existing']);
        $template = ReportTemplate::query()->create(['name' => 'T', 'blocks' => [['type' => 'cover', 'heading' => 'Cover']]]);

        Livewire::actingAs(User::factory()->manager()->create())
            ->test(Builder::class, ['report' => $report])
            ->call('applyTemplate', $template->id);

        $this->assertCount(1, $report->blocks()->get());
    }
}
