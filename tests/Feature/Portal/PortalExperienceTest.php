<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Livewire\Portal\Dashboard;
use App\Mail\ReportMail;
use App\Models\Client;
use App\Models\Report;
use App\Models\ReportShare;
use App\Models\Site;
use App\Models\User;
use App\Reporting\ReportGenerator;
use App\Reporting\ReportShareService;
use App\Support\Branding\BrandingResolver;
use App\Support\ReportLang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PortalExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function generated(Site $site, string $title, string $end): Report
    {
        $report = Report::factory()->for($site)->create([
            'title' => $title,
            'range_start' => substr($end, 0, 8).'01',
            'range_end' => $end,
        ]);
        $report->blocks()->create(['type' => 'cover', 'position' => 0]);
        app(ReportGenerator::class)->generate($report);

        return $report->refresh();
    }

    private function brandAgency(): void
    {
        $profile = app(BrandingResolver::class)->global();
        $profile->forceFill([
            'agency_name' => 'Harbour Studio',
            'tagline' => 'Websites that earn their keep',
            'email' => 'hello@harbour.test',
            'website' => 'https://harbour.test',
        ])->save();
    }

    public function test_the_portal_is_branded_groups_reports_by_year_and_offers_pdfs(): void
    {
        $this->brandAgency();
        $client = Client::factory()->create(['name' => 'Northwind']);
        $site = Site::factory()->for($client)->create(['name' => 'Northwind Site']);
        $old = $this->generated($site, 'December roundup', '2025-12-31');
        $new = $this->generated($site, 'August roundup', '2026-08-31');
        $user = User::factory()->client()->create(['client_id' => $client->id]);

        $this->actingAs($user)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('<title>Your reports · Harbour Studio</title>', false)
            ->assertSee('Harbour Studio')
            ->assertSee('hello@harbour.test')
            ->assertSee('<nav aria-label="Main"', false)
            ->assertSee('Profile')
            ->assertSeeInOrder(['2026', 'August roundup', '2025', 'December roundup'])
            ->assertSee(route('portal.report.pdf', $new), false)
            ->assertSee('Northwind Site')
            ->assertDontSee('Client Reporter');

        // The old report is still there — just under its own year.
        $this->assertTrue($old->isGenerated());
    }

    public function test_a_client_can_download_their_own_pdf_but_not_another_clients(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $mine = $this->generated(Site::factory()->for($client)->create(), 'Mine', '2026-08-31');
        $theirs = $this->generated(Site::factory()->for($other)->create(), 'Theirs', '2026-08-31');
        $user = User::factory()->client()->create(['client_id' => $client->id]);

        $this->actingAs($user)->get(route('portal.report.pdf', $mine))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)->get(route('portal.report.pdf', $theirs))->assertForbidden();

        $mine->update(['status' => 'draft']);
        $this->actingAs($user)->get(route('portal.report.pdf', $mine))->assertNotFound();
    }

    public function test_reports_can_be_narrowed_to_one_website(): void
    {
        $client = Client::factory()->create();
        $alpha = Site::factory()->for($client)->create(['name' => 'Alpha']);
        $beta = Site::factory()->for($client)->create(['name' => 'Beta']);
        $this->generated($alpha, 'Alpha report', '2026-08-31');
        $this->generated($beta, 'Beta report', '2026-08-31');
        $user = User::factory()->client()->create(['client_id' => $client->id]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Alpha report')
            ->assertSee('Beta report')
            ->set('site', $alpha->id)
            ->assertSee('Alpha report')
            ->assertDontSee('Beta report');
    }

    public function test_a_client_users_profile_and_security_pages_use_the_portal_shell(): void
    {
        $this->brandAgency();
        $client = Client::factory()->create();
        $user = User::factory()->client()->create(['client_id' => $client->id]);

        foreach (['/settings/profile', '/settings/two-factor'] as $path) {
            $this->actingAs($user)->get($path)
                ->assertOk()
                ->assertSee('Harbour Studio')
                ->assertDontSee('Dashboard')
                ->assertDontSee('Integrations');
        }

        $staff = User::factory()->manager()->create();
        $this->actingAs($staff)->get('/settings/profile')->assertOk()->assertSee('Integrations');
    }

    public function test_the_sign_in_page_carries_the_agency_branding_once_it_is_set(): void
    {
        $this->get('/login')->assertOk()->assertSee('Self-hosted client reporting for web agencies.');

        $this->brandAgency();

        $this->get('/login')
            ->assertOk()
            ->assertSee('<title>Sign in · Harbour Studio</title>', false)
            ->assertSee('Harbour Studio')
            ->assertSee('Websites that earn their keep')
            ->assertDontSee('Self-hosted client reporting');
    }

    public function test_share_gate_pages_carry_the_agency_branding(): void
    {
        $this->brandAgency();
        $client = Client::factory()->create();
        $site = Site::factory()->for($client)->create();
        $report = $this->generated($site, 'Protected', '2026-08-31');
        $result = app(ReportShareService::class)->create($report, null, 'a-secret-passphrase');
        $token = $result['token'];

        $this->get(route('public-report', ['token' => $token]))
            ->assertOk()
            ->assertSee('Harbour Studio')
            ->assertSee('This report is protected')
            ->assertSee('hello@harbour.test');

        ReportShare::query()->update(['revoked_at' => now()]);
        $this->get(route('public-report', ['token' => $token]))
            ->assertNotFound()
            ->assertSee('Harbour Studio')
            ->assertSee('no longer available');

        $this->get(route('public-report', ['token' => str_repeat('a', 40)]))
            ->assertNotFound()
            ->assertSee('Harbour Studio');
    }

    public function test_the_report_email_has_a_plain_text_part_in_the_agency_voice(): void
    {
        $this->brandAgency();
        $client = Client::factory()->create();
        $site = Site::factory()->for($client)->create(['name' => 'Northwind Site']);
        $report = $this->generated($site, 'August roundup', '2026-08-31');
        $branding = app(BrandingResolver::class)->forSite($site);

        $mail = new ReportMail($report, 'https://example.test/r/abc', $branding, 'Here is your August report.');

        $mail->assertSeeInText('August roundup')
            ->assertSeeInText('Here is your August report.')
            ->assertSeeInText('https://example.test/r/abc')
            ->assertSeeInHtml('August roundup')
            ->assertDontSeeInText('Client Reporter');
    }

    public function test_a_generated_report_never_prints_the_empty_text_placeholder(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        $report = Report::factory()->for($site)->create();
        $report->blocks()->create(['type' => 'text', 'position' => 0, 'heading' => 'A note']);

        $placeholder = ReportLang::get('text.empty');

        $this->actingAs($manager)->get(route('reports.preview', $report))
            ->assertOk()
            ->assertSee($placeholder);

        app(ReportGenerator::class)->generate($report->refresh());

        $this->actingAs($manager)->get(route('reports.preview', ['report' => $report, 'frozen' => 1]))
            ->assertOk()
            ->assertSee('A note')
            ->assertDontSee($placeholder);
    }
}
