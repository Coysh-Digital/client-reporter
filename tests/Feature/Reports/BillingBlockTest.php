<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\InvoiceStatus;
use App\Livewire\Reports\Builder;
use App\Models\Invoice;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use App\Reporting\BlockAvailability;
use App\Reporting\BlockTypeRegistry;
use App\Reporting\ReportGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BillingBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_billing_block_is_unavailable_with_no_invoices(): void
    {
        $site = Site::factory()->create();
        $type = app(BlockTypeRegistry::class)->find('billing.summary');

        $this->assertFalse(app(BlockAvailability::class)->isAvailable($type, $site, []));
    }

    public function test_billing_block_becomes_available_once_the_client_has_an_invoice(): void
    {
        $site = Site::factory()->create();
        Invoice::factory()->for($site->client)->create();

        $type = app(BlockTypeRegistry::class)->find('billing.summary');
        $this->assertTrue(app(BlockAvailability::class)->isAvailable($type, $site, []));

        $manager = User::factory()->manager()->create();
        $report = Report::factory()->for($site)->create();
        Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->assertSee('Billing & invoices');
    }

    public function test_billing_block_resolves_metrics_and_insight_for_the_period(): void
    {
        $site = Site::factory()->create();
        $client = $site->client;

        Invoice::factory()->for($client)->create([
            'number' => 'INV-1', 'amount' => 1850, 'status' => InvoiceStatus::Paid,
            'issued_at' => '2026-08-05', 'due_at' => '2026-08-19', 'paid_at' => '2026-08-10',
        ]);
        Invoice::factory()->for($client)->create([
            'number' => 'INV-2', 'amount' => 2400, 'status' => InvoiceStatus::Sent,
            'issued_at' => '2026-08-20', 'due_at' => '2026-07-01',
        ]);
        // Outside the report period — must not be counted.
        Invoice::factory()->for($client)->create([
            'number' => 'INV-3', 'amount' => 9999, 'status' => InvoiceStatus::Paid,
            'issued_at' => '2026-06-01',
        ]);

        $report = Report::factory()->for($site)->create([
            'range_start' => '2026-08-01', 'range_end' => '2026-08-31',
        ]);
        $report->blocks()->create(['type' => 'billing.summary', 'position' => 0, 'heading' => 'Billing & invoices']);

        app(ReportGenerator::class)->generate($report);
        $report->refresh();

        $block = collect($report->latestRender->data)->firstWhere('type', 'billing.summary');
        $metrics = collect($block['data']['metrics'])->keyBy('label');

        $this->assertEqualsWithDelta(4250.0, $metrics['Invoiced']['current'], 0.001);
        $this->assertEqualsWithDelta(1850.0, $metrics['Paid']['current'], 0.001);
        $this->assertEqualsWithDelta(2400.0, $metrics['Outstanding']['current'], 0.001);
        $this->assertEqualsWithDelta(1.0, $metrics['Overdue']['current'], 0.001);
        $this->assertStringContainsString('overdue', $block['data']['insight']);
    }

    public function test_outstanding_and_overdue_span_all_periods_and_drafts_are_excluded(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 9, 1));

        $site = Site::factory()->create();
        $client = $site->client;

        // A draft raised in the period — must be ignored in every figure and the table.
        Invoice::factory()->for($client)->create([
            'number' => 'DRAFT-1', 'amount' => 500, 'status' => InvoiceStatus::Draft,
            'issued_at' => '2026-08-10',
        ]);
        // Sent and overdue, raised BEFORE the period — must still count as outstanding/overdue.
        Invoice::factory()->for($client)->create([
            'number' => 'OLD-1', 'amount' => 1200, 'status' => InvoiceStatus::Sent,
            'issued_at' => '2026-05-01', 'due_at' => '2026-05-15',
        ]);
        // Paid in the period.
        Invoice::factory()->for($client)->create([
            'number' => 'INV-1', 'amount' => 800, 'status' => InvoiceStatus::Paid,
            'issued_at' => '2026-08-05', 'paid_at' => '2026-08-06',
        ]);

        $report = Report::factory()->for($site)->create(['range_start' => '2026-08-01', 'range_end' => '2026-08-31']);
        $report->blocks()->create(['type' => 'billing.summary', 'position' => 0, 'heading' => 'Billing & invoices']);
        app(ReportGenerator::class)->generate($report);
        $report->refresh();

        $block = collect($report->latestRender->data)->firstWhere('type', 'billing.summary');
        $metrics = collect($block['data']['metrics'])->keyBy('label');

        // Invoiced/paid stay period-scoped and exclude the draft.
        $this->assertEqualsWithDelta(800.0, $metrics['Invoiced']['current'], 0.001);
        $this->assertEqualsWithDelta(800.0, $metrics['Paid']['current'], 0.001);
        // Outstanding & overdue include the older invoice from outside the period.
        $this->assertEqualsWithDelta(1200.0, $metrics['Outstanding']['current'], 0.001);
        $this->assertEqualsWithDelta(1.0, $metrics['Overdue']['current'], 0.001);

        $numbers = collect($block['data']['invoices'])->pluck('number');
        $this->assertFalse($numbers->contains('DRAFT-1'), 'Draft invoices must not appear in the table.');
        $this->assertTrue($numbers->contains('INV-1'));
        $this->assertFalse($numbers->contains('OLD-1'), 'Out-of-period invoices are not listed in the period table.');
    }

    public function test_block_reports_outstanding_even_when_nothing_was_raised_in_the_period(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 9, 1));

        $site = Site::factory()->create();
        Invoice::factory()->for($site->client)->create([
            'number' => 'OLD-1', 'amount' => 640, 'status' => InvoiceStatus::Sent,
            'issued_at' => '2026-05-01', 'due_at' => '2026-11-01',
        ]);

        $report = Report::factory()->for($site)->create(['range_start' => '2026-08-01', 'range_end' => '2026-08-31']);
        $report->blocks()->create(['type' => 'billing.summary', 'position' => 0, 'heading' => 'Billing & invoices']);
        app(ReportGenerator::class)->generate($report);
        $report->refresh();

        $block = collect($report->latestRender->data)->firstWhere('type', 'billing.summary');
        $this->assertTrue($block['data']['has_data']);

        $metrics = collect($block['data']['metrics'])->keyBy('label');
        $this->assertEqualsWithDelta(0.0, $metrics['Invoiced']['current'], 0.001);
        $this->assertEqualsWithDelta(640.0, $metrics['Outstanding']['current'], 0.001);
        $this->assertStringContainsString('outstanding', $block['data']['insight']);
    }
}
