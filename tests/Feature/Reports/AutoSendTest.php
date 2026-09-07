<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\DeliveryTrigger;
use App\Jobs\GenerateReport;
use App\Mail\ReportMail;
use App\Models\Client;
use App\Models\Report;
use App\Models\ReportDelivery;
use App\Models\Site;
use App\Models\User;
use App\Reporting\ReportSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class AutoSendTest extends TestCase
{
    use RefreshDatabase;

    private function scheduledReport(bool $autoSend, bool $scheduled = true, ?string $contactEmail = 'client@example.com'): Report
    {
        $client = Client::factory()->create(['contact_email' => $contactEmail]);
        $site = Site::factory()->for($client)->create(['report_frequency' => 'monthly', 'auto_send' => $autoSend]);
        $report = Report::factory()->for($site)->create(['scheduled' => $scheduled]);
        $report->blocks()->create(['type' => 'cover', 'position' => 0, 'heading' => 'Cover']);

        return $report;
    }

    public function test_a_scheduled_report_is_emailed_to_the_client_when_auto_send_is_on(): void
    {
        Http::fake();
        Mail::fake();
        Pdf::fake();

        $report = $this->scheduledReport(autoSend: true);

        GenerateReport::queueFor($report); // sync queue runs generation + auto-send inline

        Mail::assertSent(ReportMail::class, fn (ReportMail $mail): bool => $mail->hasTo('client@example.com'));
        $this->assertDatabaseHas('report_deliveries', [
            'report_id' => $report->id,
            'recipient' => 'client@example.com',
            'trigger' => 'auto',
            'succeeded' => true,
        ]);
    }

    public function test_nothing_is_sent_when_auto_send_is_off(): void
    {
        Http::fake();
        Mail::fake();

        $report = $this->scheduledReport(autoSend: false);

        GenerateReport::queueFor($report);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('report_deliveries', 0);
    }

    public function test_a_manual_report_never_auto_sends_even_when_the_site_opts_in(): void
    {
        Http::fake();
        Mail::fake();

        $report = $this->scheduledReport(autoSend: true, scheduled: false);

        GenerateReport::queueFor($report);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('report_deliveries', 0);
    }

    public function test_a_missing_contact_email_is_flagged_rather_than_sent(): void
    {
        Http::fake();
        Mail::fake();
        Pdf::fake();

        $report = $this->scheduledReport(autoSend: true, contactEmail: null);

        GenerateReport::queueFor($report);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('report_deliveries', [
            'report_id' => $report->id,
            'trigger' => 'auto',
            'succeeded' => false,
        ]);
    }

    public function test_regenerating_a_sent_report_does_not_send_it_again(): void
    {
        Http::fake();
        Mail::fake();
        Pdf::fake();

        $report = $this->scheduledReport(autoSend: true);

        GenerateReport::queueFor($report);
        GenerateReport::queueFor($report);

        Mail::assertSent(ReportMail::class, 1);
        $this->assertSame(1, ReportDelivery::query()->where('report_id', $report->id)->where('succeeded', true)->count());
    }

    public function test_the_sender_records_a_successful_manual_delivery(): void
    {
        Mail::fake();

        $report = Report::factory()->for(Site::factory())->create();
        $user = User::factory()->manager()->create();

        $delivery = app(ReportSender::class)->send(
            report: $report,
            to: 'someone@example.com',
            customMessage: 'Here you go',
            attachPdf: false,
            trigger: DeliveryTrigger::Manual,
            actor: $user,
        );

        Mail::assertSent(ReportMail::class, fn (ReportMail $mail): bool => $mail->hasTo('someone@example.com'));
        $this->assertTrue($delivery->succeeded);
        $this->assertSame('someone@example.com', $delivery->recipient);
        $this->assertSame(DeliveryTrigger::Manual, $delivery->trigger);
        $this->assertFalse($delivery->included_pdf);
        $this->assertSame($user->id, $delivery->created_by);
    }
}
