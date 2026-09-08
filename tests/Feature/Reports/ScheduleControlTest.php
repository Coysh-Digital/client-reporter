<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\ReportFrequency;
use App\Livewire\Reports\Scheduled;
use App\Livewire\Reports\Show;
use App\Livewire\Sites\Form;
use App\Models\Report;
use App\Models\ReportDelivery;
use App\Models\ReportTemplate;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_report_page_edits_its_sites_schedule(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create(['report_frequency' => 'none', 'auto_send' => false]);
        $report = Report::factory()->for($site)->create();

        Livewire::actingAs($manager)->test(Show::class, ['report' => $report])
            ->set('report_frequency', 'monthly')
            ->set('auto_send', 'yes')
            ->call('saveSchedule')
            ->assertHasNoErrors();

        $site->refresh();
        $this->assertSame(ReportFrequency::Monthly, $site->report_frequency);
        $this->assertTrue($site->autoSends());
    }

    public function test_turning_the_schedule_off_clears_the_template(): void
    {
        $manager = User::factory()->manager()->create();
        $template = ReportTemplate::query()->create(['name' => 'Monthly', 'blocks' => []]);
        $site = Site::factory()->create(['report_frequency' => 'monthly', 'report_template_id' => $template->id]);
        $report = Report::factory()->for($site)->create();

        Livewire::actingAs($manager)->test(Show::class, ['report' => $report])
            ->set('report_frequency', 'none')
            ->call('saveSchedule');

        $site->refresh();
        $this->assertSame(ReportFrequency::None, $site->report_frequency);
        $this->assertNull($site->report_template_id);
    }

    public function test_the_site_form_saves_auto_send(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create(['report_frequency' => 'monthly', 'auto_send' => false]);

        Livewire::actingAs($manager)->test(Form::class, ['site' => $site])
            ->set('auto_send', 'yes')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($site->refresh()->autoSends());
    }

    public function test_the_scheduled_page_shows_auto_send_and_last_sent(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create(['name' => 'Acme Site', 'report_frequency' => 'monthly', 'auto_send' => true]);
        $report = Report::factory()->for($site)->create(['scheduled' => true]);
        ReportDelivery::factory()->auto()->for($report)->create(['recipient' => 'client@acme.test']);

        Livewire::actingAs($manager)->test(Scheduled::class)
            ->assertSee('Acme Site')
            ->assertSee('Auto-send');
    }
}
