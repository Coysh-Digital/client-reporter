<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Enums\ConnectionStatus;
use App\Mcp\Servers\ClientReporterServer;
use App\Mcp\Tools\GetDashboardTool;
use App\Mcp\Tools\GetReportTool;
use App\Mcp\Tools\GetSiteMetricsTool;
use App\Mcp\Tools\GetSiteTool;
use App\Mcp\Tools\ListClientsTool;
use App\Mcp\Tools\ListReportsTool;
use App\Mcp\Tools\ListSitesTool;
use App\Models\Client;
use App\Models\Metric;
use App\Models\Report;
use App\Models\ReportRender;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientReporterServerTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->administrator()->create();
    }

    public function test_list_clients_returns_clients_for_staff(): void
    {
        Client::factory()->create(['name' => 'Northwind Cafe']);

        ClientReporterServer::actingAs($this->staff())
            ->tool(ListClientsTool::class)
            ->assertOk()
            ->assertHasNoErrors()
            ->assertSee('Northwind Cafe');
    }

    public function test_a_client_portal_user_is_denied(): void
    {
        $client = Client::factory()->create();
        $portalUser = User::factory()->client()->create(['client_id' => $client->id]);

        ClientReporterServer::actingAs($portalUser)
            ->tool(ListClientsTool::class)
            ->assertHasErrors();
    }

    public function test_a_deactivated_staff_user_is_denied(): void
    {
        $user = User::factory()->administrator()->inactive()->create();

        ClientReporterServer::actingAs($user)
            ->tool(ListClientsTool::class)
            ->assertHasErrors();
    }

    public function test_the_local_stdio_context_without_a_user_is_trusted(): void
    {
        Client::factory()->create(['name' => 'Local Trusted Co']);

        // No actingAs() — mirrors the stdio transport where there is no
        // authenticated user; the operator running the command is trusted.
        ClientReporterServer::tool(ListClientsTool::class)
            ->assertOk()
            ->assertSee('Local Trusted Co');
    }

    public function test_a_viewer_can_read_sites_with_health(): void
    {
        $site = Site::factory()->create(['name' => 'Acme Marketing Site']);

        ClientReporterServer::actingAs(User::factory()->viewer()->create())
            ->tool(ListSitesTool::class, ['client_id' => $site->client_id])
            ->assertOk()
            ->assertHasNoErrors()
            ->assertSee('Acme Marketing Site');
    }

    public function test_get_site_returns_integrations_but_never_credentials(): void
    {
        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'plausible',
            'name' => 'Plausible',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['api_key' => 'super-secret-value'],
        ]);

        ClientReporterServer::actingAs($this->staff())
            ->tool(GetSiteTool::class, ['site_id' => $site->id])
            ->assertOk()
            ->assertSee('plausible')
            ->assertDontSee('super-secret-value');
    }

    public function test_get_site_errors_for_a_missing_site(): void
    {
        ClientReporterServer::actingAs($this->staff())
            ->tool(GetSiteTool::class, ['site_id' => 99999])
            ->assertHasErrors();
    }

    public function test_list_reports_filters_by_site(): void
    {
        $site = Site::factory()->create();
        Report::factory()->for($site)->create(['title' => 'September Report', 'status' => 'final']);

        ClientReporterServer::actingAs($this->staff())
            ->tool(ListReportsTool::class, ['site_id' => $site->id])
            ->assertOk()
            ->assertSee('September Report');
    }

    public function test_get_report_errors_when_not_generated(): void
    {
        $report = Report::factory()->create(['status' => 'draft', 'generated_at' => null]);

        ClientReporterServer::actingAs($this->staff())
            ->tool(GetReportTool::class, ['report_id' => $report->id])
            ->assertHasErrors();
    }

    public function test_get_report_returns_frozen_block_data(): void
    {
        $report = Report::factory()->create(['status' => 'final', 'generated_at' => now()]);
        ReportRender::create([
            'report_id' => $report->id,
            'rendered_at' => now(),
            'data' => [
                101 => [
                    'type' => 'analytics.summary',
                    'heading' => 'Traffic',
                    'commentary' => null,
                    'data' => ['visitors' => 9876],
                ],
            ],
            'branding_snapshot' => [],
            'meta' => ['range' => ['start' => '2026-09-01', 'end' => '2026-09-30'], 'comparison' => null],
        ]);

        ClientReporterServer::actingAs($this->staff())
            ->tool(GetReportTool::class, ['report_id' => $report->id])
            ->assertOk()
            ->assertHasNoErrors()
            ->assertSee('Traffic')
            ->assertSee('9876');
    }

    public function test_get_site_metrics_reads_collected_metrics_for_a_period(): void
    {
        $site = Site::factory()->create();
        $connection = SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'plausible',
            'name' => 'Plausible',
            'status' => ConnectionStatus::Connected,
        ]);

        $range = DateRange::thisMonth();
        Metric::create([
            'site_integration_id' => $connection->id,
            'metric_key' => 'analytics.visitors',
            'period_start' => $range->start->toDateString(),
            'period_end' => $range->end->toDateString(),
            'value' => 1234,
            'unit' => null,
            'meta' => [],
            'captured_at' => now(),
        ]);

        ClientReporterServer::actingAs($this->staff())
            ->tool(GetSiteMetricsTool::class, ['site_id' => $site->id, 'period' => 'this_month'])
            ->assertOk()
            ->assertSee('analytics.visitors')
            ->assertSee('1234');
    }

    public function test_get_dashboard_returns_portfolio_totals(): void
    {
        Site::factory()->create();

        ClientReporterServer::actingAs($this->staff())
            ->tool(GetDashboardTool::class)
            ->assertOk()
            ->assertHasNoErrors()
            ->assertSee('reports_to_prepare');
    }

    /**
     * A minimal MCP `initialize` request — the protocol middleware passes these
     * straight through to the auth layer, so it exercises our auth/ability
     * middleware rather than being rejected as a malformed MCP message.
     *
     * @return array<string, mixed>
     */
    private function initializeBody(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1'],
            ],
        ];
    }

    public function test_the_http_endpoint_rejects_an_unauthenticated_request(): void
    {
        $this->postJson('/mcp', $this->initializeBody(), [
            'Accept' => 'application/json, text/event-stream',
        ])->assertStatus(401);
    }

    public function test_the_http_endpoint_rejects_a_token_without_the_mcp_ability(): void
    {
        Sanctum::actingAs($this->staff(), []);

        $this->postJson('/mcp', $this->initializeBody(), [
            'Accept' => 'application/json, text/event-stream',
        ])->assertStatus(403);
    }

    public function test_the_http_endpoint_lets_a_token_with_the_mcp_ability_through_auth(): void
    {
        Sanctum::actingAs($this->staff(), ['mcp:read']);

        // A correct token clears both the auth and ability middleware; the exact
        // success status is an MCP transport detail, so we only assert it is not
        // blocked by our auth layer (401) or the ability gate (403).
        $response = $this->postJson('/mcp', $this->initializeBody(), [
            'Accept' => 'application/json, text/event-stream',
        ]);

        $this->assertNotContains($response->status(), [401, 403]);
    }
}
