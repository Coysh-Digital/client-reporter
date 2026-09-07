<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\ReportFrequency;
use App\Livewire\Reports\Scheduled;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduledPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_renders_for_a_manager(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('reports.scheduled'))->assertOk()->assertSee('Scheduled reports');
    }

    public function test_it_lists_scheduled_active_sites_only(): void
    {
        $manager = User::factory()->manager()->create();

        Site::factory()->create(['name' => 'Acme Monthly', 'report_frequency' => ReportFrequency::Monthly]);
        Site::factory()->create(['name' => 'Manual Site', 'report_frequency' => ReportFrequency::None]);
        Site::factory()->inactive()->create(['name' => 'Dormant Weekly', 'report_frequency' => ReportFrequency::Weekly]);

        Livewire::actingAs($manager)->test(Scheduled::class)
            ->assertSee('Acme Monthly')
            ->assertSee('Monthly')
            ->assertDontSee('Manual Site')
            ->assertDontSee('Dormant Weekly');
    }

    public function test_it_shows_an_empty_state_when_nothing_is_scheduled(): void
    {
        $manager = User::factory()->manager()->create();
        Site::factory()->create(['report_frequency' => ReportFrequency::None]);

        Livewire::actingAs($manager)->test(Scheduled::class)
            ->assertSee('No sites are scheduled yet');
    }
}
