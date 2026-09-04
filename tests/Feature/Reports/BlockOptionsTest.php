<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\ConnectionStatus;
use App\Livewire\Reports\Builder;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Reporting\Blocks\EcommerceBlock;
use App\Reporting\BlockTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlockOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ecommerce_block_is_now_a_single_generic_type(): void
    {
        $registry = app(BlockTypeRegistry::class);
        $this->assertInstanceOf(EcommerceBlock::class, $registry->find('ecommerce.summary'));
        // The old Craft-specific ecommerce type is gone (consolidated).
        $this->assertNull($registry->find('ecommerce.craft'));
    }

    public function test_add_menu_hides_blocks_without_a_data_source(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        $report = Report::factory()->for($site)->create();

        Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->assertSee('Cover')
            ->assertDontSee('Analytics summary')
            ->assertDontSee('Uptime summary');
    }

    public function test_add_menu_reveals_analytics_when_a_provider_is_connected(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'plausible', 'status' => ConnectionStatus::Connected,
        ]);
        $report = Report::factory()->for($site)->create();

        Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->assertSee('Analytics summary');
    }

    public function test_adding_a_block_seeds_defaults_and_persist_normalises_config(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        $report = Report::factory()->for($site)->create();

        $component = Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->call('addBlock', 'analytics.top_pages');

        $block = $report->blocks()->where('type', 'analytics.top_pages')->firstOrFail();
        $this->assertSame(8, $block->config['limit']);

        // Out-of-range values are clamped to the option's max (25).
        $component->set("edits.{$block->id}.config.limit", 999)->call('persistBlock', $block->id);
        $this->assertSame(25, $block->fresh()->config['limit']);
    }
}
