<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Livewire\Reports\Create;
use App\Livewire\Templates\Form;
use App\Livewire\Templates\Index;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_viewer_cannot_open_templates(): void
    {
        $viewer = User::factory()->viewer()->create();
        $this->actingAs($viewer)->get('/templates')->assertForbidden();
    }

    public function test_a_manager_can_create_a_template_with_normalised_block_config(): void
    {
        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(Form::class)
            ->set('name', 'Standard care report')
            ->set('description', 'Cover, analytics and a closing note.')
            ->call('addBlock', 'cover')
            ->call('addBlock', 'analytics.summary')
            ->call('save')
            ->assertHasNoErrors();

        $template = ReportTemplate::query()->firstOrFail();
        $this->assertSame('Standard care report', $template->name);
        $this->assertCount(2, $template->blocks);
        $this->assertSame('cover', $template->blocks[0]['type']);
        // The analytics block's default options were seeded + normalised.
        $this->assertArrayHasKey('compare', $template->blocks[1]['config']);
        $this->assertTrue($template->blocks[1]['config']['compare']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'report_template.created']);
    }

    public function test_editing_loads_and_updates_blocks(): void
    {
        $manager = User::factory()->manager()->create();
        $template = ReportTemplate::query()->create([
            'name' => 'Old name',
            'blocks' => [['type' => 'cover', 'heading' => 'Cover', 'config' => []]],
        ]);

        Livewire::actingAs($manager)->test(Form::class, ['template' => $template])
            ->assertSet('name', 'Old name')
            ->set('name', 'New name')
            ->call('addBlock', 'text')
            ->call('save')
            ->assertHasNoErrors();

        $template->refresh();
        $this->assertSame('New name', $template->name);
        $this->assertCount(2, $template->blocks);
    }

    public function test_deleting_a_template(): void
    {
        $manager = User::factory()->manager()->create();
        $template = ReportTemplate::query()->create(['name' => 'Temp', 'blocks' => []]);

        Livewire::actingAs($manager)->test(Index::class)->call('delete', $template->id);

        $this->assertDatabaseMissing('report_templates', ['id' => $template->id]);
    }

    public function test_a_template_seeds_a_report_only_with_available_blocks(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create(); // no integrations connected
        ReportTemplate::query()->create([
            'name' => 'Full',
            'blocks' => [
                ['type' => 'cover', 'heading' => 'Cover'],
                ['type' => 'analytics.summary', 'heading' => 'Analytics'],
                ['type' => 'closing', 'heading' => 'Thanks'],
            ],
        ]);
        $templateId = ReportTemplate::query()->value('id');

        Livewire::actingAs($manager)->test(Create::class)
            ->set('site_id', $site->id)
            ->set('title', 'August report')
            ->set('report_template_id', $templateId)
            ->call('save')
            ->assertHasNoErrors();

        $report = Report::query()->firstOrFail();
        $types = $report->blocks()->pluck('type')->all();
        $this->assertContains('cover', $types);
        $this->assertContains('closing', $types);
        // Skipped — the site has no analytics integration.
        $this->assertNotContains('analytics.summary', $types);
    }
}
