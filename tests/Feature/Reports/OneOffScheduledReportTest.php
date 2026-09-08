<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Livewire\Reports\Create;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class OneOffScheduledReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_dated_report_stores_the_schedule_and_opens_its_page(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        Livewire::actingAs($manager)->test(Create::class)
            ->set('site_id', $site->id)
            ->set('title', 'Quarterly review')
            ->set('generate_when', 'date')
            ->set('scheduled_for', '2026-10-01')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $report = Report::query()->where('site_id', $site->id)->firstOrFail();
        $this->assertTrue($report->scheduled);
        $this->assertSame('2026-10-01', $report->scheduled_for->toDateString());
        $this->assertNull($report->generated_at);
        $this->assertTrue($report->isAwaitingScheduledGeneration());
    }

    public function test_a_date_in_the_past_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        Livewire::actingAs($manager)->test(Create::class)
            ->set('site_id', $site->id)
            ->set('title', 'Backdated')
            ->set('generate_when', 'date')
            ->set('scheduled_for', now()->subDay()->toDateString())
            ->call('save')
            ->assertHasErrors('scheduled_for');

        $this->assertSame(0, Report::query()->count());
    }

    public function test_the_command_generates_a_dated_report_on_its_date_but_not_before(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $site = Site::factory()->create(['report_frequency' => 'none']);
        $report = Report::factory()->for($site)->create([
            'scheduled' => true,
            'scheduled_for' => '2026-09-20',
            'generated_at' => null,
        ]);

        // A day before its date, the command leaves it alone.
        $this->travelTo('2026-09-19 07:00:00');
        $this->artisan('client-reporter:generate-scheduled')->assertSuccessful();
        $this->assertNull($report->fresh()->generated_at);

        // On its date it generates.
        $this->travelTo('2026-09-20 07:00:00');
        $this->artisan('client-reporter:generate-scheduled')->assertSuccessful();
        $this->assertNotNull($report->fresh()->generated_at);

        $this->travelBack();
    }

    public function test_an_already_generated_dated_report_is_not_generated_again(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $site = Site::factory()->create(['report_frequency' => 'none']);
        $report = Report::factory()->for($site)->create([
            'scheduled' => true,
            'scheduled_for' => '2026-09-01',
            'generated_at' => now(),
        ]);
        $generatedAt = $report->generated_at;

        $this->travelTo('2026-09-20 07:00:00');
        $this->artisan('client-reporter:generate-scheduled')->assertSuccessful();
        $this->travelBack();

        // Untouched: generated_at is unchanged and no queued status was set.
        $this->assertEquals($generatedAt->toDateTimeString(), $report->fresh()->generated_at->toDateTimeString());
        $this->assertNull($report->fresh()->generation_status);
    }
}
