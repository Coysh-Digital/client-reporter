<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Livewire\Reports\SharePanel;
use App\Mail\ReportMail;
use App\Models\Client;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use App\Reporting\ReportGenerator;
use App\Reporting\ReportShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SharingTest extends TestCase
{
    use RefreshDatabase;

    private function generatedReport(): Report
    {
        $site = Site::factory()->create();
        $report = Report::factory()->for($site)->create();
        $report->blocks()->create(['type' => 'cover', 'position' => 0, 'heading' => 'Cover']);
        $report->blocks()->create(['type' => 'text', 'position' => 1, 'heading' => 'Intro', 'commentary' => 'Hello world']);

        app(ReportGenerator::class)->generate($report);

        return $report->refresh();
    }

    public function test_a_share_link_renders_the_report_publicly(): void
    {
        $report = $this->generatedReport();
        $result = app(ReportShareService::class)->create($report);

        $this->get(app(ReportShareService::class)->url($result['token']))
            ->assertOk()
            ->assertSee($report->site->client->name);

        $this->assertSame(1, $result['share']->refresh()->views);
    }

    public function test_an_expired_link_is_not_accessible(): void
    {
        $report = $this->generatedReport();
        $result = app(ReportShareService::class)->create($report, expiresInDays: 1);
        $result['share']->update(['expires_at' => now()->subDay()]);

        $this->get(app(ReportShareService::class)->url($result['token']))->assertNotFound();
    }

    public function test_a_revoked_link_is_not_accessible(): void
    {
        $report = $this->generatedReport();
        $result = app(ReportShareService::class)->create($report);
        $result['share']->update(['revoked_at' => now()]);

        $this->get(app(ReportShareService::class)->url($result['token']))->assertNotFound();
    }

    public function test_a_password_protected_link_requires_the_password(): void
    {
        $report = $this->generatedReport();
        $shares = app(ReportShareService::class);
        $result = $shares->create($report, password: 'letmein');
        $url = $shares->url($result['token']);

        // Without unlocking, the password form is shown (not the report).
        $this->get($url)->assertOk()->assertSee('This report is protected')->assertDontSee($report->site->client->name);

        // Wrong password fails.
        $this->post(route('public-report.unlock', ['token' => $result['token']]), ['password' => 'nope'])
            ->assertSee('was not correct');

        // Correct password unlocks for the session.
        $this->post(route('public-report.unlock', ['token' => $result['token']]), ['password' => 'letmein'])
            ->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee($report->site->client->name);
    }

    public function test_tokens_are_stored_only_as_a_hash(): void
    {
        $report = $this->generatedReport();
        $result = app(ReportShareService::class)->create($report);

        $this->assertDatabaseMissing('report_shares', ['token_hash' => $result['token']]);
        $this->assertDatabaseHas('report_shares', ['token_hash' => hash('sha256', $result['token'])]);
    }

    public function test_pdf_download_returns_a_pdf(): void
    {
        $admin = User::factory()->administrator()->create();
        $report = $this->generatedReport();

        $response = $this->actingAs($admin)->get(route('reports.pdf', $report));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_a_report_can_be_emailed_to_the_client(): void
    {
        Mail::fake();
        $client = Client::factory()->create(['contact_email' => 'client@acme.test']);
        $site = Site::factory()->for($client)->create();
        $report = Report::factory()->for($site)->create();
        $report->blocks()->create(['type' => 'cover', 'position' => 0]);
        app(ReportGenerator::class)->generate($report);

        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(SharePanel::class, ['report' => $report->refresh()])
            ->set('attachPdf', false)
            ->call('sendEmail')
            ->assertHasNoErrors();

        Mail::assertSent(ReportMail::class, fn (ReportMail $mail) => $mail->hasTo('client@acme.test'));
    }

    public function test_sharing_requires_a_generated_report(): void
    {
        $manager = User::factory()->manager()->create();
        $report = Report::factory()->create(); // draft

        Livewire::actingAs($manager)->test(SharePanel::class, ['report' => $report])
            ->call('createLink')
            ->assertHasErrors('generate');
    }
}
