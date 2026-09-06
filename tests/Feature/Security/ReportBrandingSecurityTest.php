<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Livewire\Branding\Manage;
use App\Models\BrandingProfile;
use App\Models\Client;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use App\Reporting\ReportGenerator;
use App\Reporting\ReportShareService;
use App\Support\Branding\ResolvedBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Branding values are injected into the <style> block of every client-facing
 * report, including unauthenticated share links. Nothing typed into the
 * branding form may ever reach that block unescaped.
 */
class ReportBrandingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function generatedReport(Client $client): Report
    {
        $site = Site::factory()->for($client)->create();
        $report = Report::factory()->for($site)->create();
        $report->blocks()->create(['type' => 'cover', 'position' => 0, 'heading' => 'Cover']);
        app(ReportGenerator::class)->generate($report);

        return $report->refresh();
    }

    public function test_a_font_outside_the_catalogue_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $client = Client::factory()->create();

        Livewire::actingAs($manager)->test(Manage::class, ['client' => $client])
            ->set('heading_font', 'x</style><script>alert(1)</script><style>')
            ->call('save')
            ->assertHasErrors('heading_font');
    }

    public function test_custom_css_may_not_contain_markup_or_remote_fetches(): void
    {
        $admin = User::factory()->administrator()->create();

        foreach ([
            '</style><script>alert(1)</script>',
            '.x { background: url(http://evil.test/a.png); }',
            '@import "http://evil.test/x.css";',
            '.x { width: expression(alert(1)); }',
        ] as $payload) {
            Livewire::actingAs($admin)->test(Manage::class)
                ->set('custom_css', $payload)
                ->call('save')
                ->assertHasErrors('custom_css');
        }

        Livewire::actingAs($admin)->test(Manage::class)
            ->set('custom_css', '.report-cover h1 { letter-spacing: -0.02em; }')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_fonts_are_stored_as_the_canonical_catalogue_stack(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Manage::class)
            ->set('heading_font', 'Merriweather')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('branding_profiles', [
            'brandable_type' => null,
            'heading_font' => "'Merriweather', Georgia, 'Times New Roman', serif",
        ]);
    }

    public function test_a_hostile_value_already_in_the_database_never_reaches_the_report(): void
    {
        $client = Client::factory()->create();
        BrandingProfile::query()->create([
            'heading_font' => "x</style><script>alert('font')</script><style>",
            'body_font' => 'Comic Sans MS</style>',
            'custom_css' => '</style><script>alert("css")</script>',
        ]);

        $report = $this->generatedReport($client);
        $shares = app(ReportShareService::class);
        $url = $shares->url($shares->create($report)['token']);

        $response = $this->get($url)->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('<script>', (string) $html);
        $this->assertStringNotContainsString("alert('font')", (string) $html);
        $this->assertStringContainsString("'Source Serif 4', Georgia", (string) $html);
        $this->assertStringContainsString("'Hanken Grotesk',", (string) $html);
    }

    public function test_resolved_branding_never_emits_unknown_fonts_or_unsafe_css(): void
    {
        $branding = ResolvedBranding::fromArray([
            'headingFont' => '</style>',
            'bodyFont' => "'Inter', sans-serif",
            'customCss' => 'body { color: red } </style>',
        ]);

        $this->assertStringStartsWith("'Source Serif 4'", $branding->headingFontStack());
        $this->assertStringStartsWith("'Inter'", $branding->bodyFontStack());
        $this->assertNull($branding->safeCustomCss());
    }

    public function test_report_documents_carry_a_strict_content_security_policy(): void
    {
        $client = Client::factory()->create();
        $report = $this->generatedReport($client);
        $shares = app(ReportShareService::class);
        $publicUrl = $shares->url($shares->create($report)['token']);

        $this->get($publicUrl)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('name="robots"', false);
        $this->assertStringContainsString("script-src 'none'", (string) $this->get($publicUrl)->headers->get('Content-Security-Policy'));

        $portalUser = User::factory()->client()->create(['client_id' => $client->id]);
        $this->assertStringContainsString(
            "script-src 'none'",
            (string) $this->actingAs($portalUser)->get(route('portal.report', $report))->headers->get('Content-Security-Policy'),
        );

        $staff = User::factory()->manager()->create();
        $this->assertStringContainsString(
            "script-src 'none'",
            (string) $this->actingAs($staff)->get(route('reports.preview', $report))->headers->get('Content-Security-Policy'),
        );
    }
}
