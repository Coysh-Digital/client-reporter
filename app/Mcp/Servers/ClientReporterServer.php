<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetDashboardTool;
use App\Mcp\Tools\GetReportTool;
use App\Mcp\Tools\GetSiteMetricsTool;
use App\Mcp\Tools\GetSiteTool;
use App\Mcp\Tools\ListClientsTool;
use App\Mcp\Tools\ListReportsTool;
use App\Mcp\Tools\ListSitesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Client Reporter')]
#[Version('0.1.0-alpha.1')]
#[Instructions(<<<'TXT'
Read-only access to a Client Reporter installation — an agency's clients, their
websites, the reports built for them, and the metrics collected behind them.

Everything here is read-only: nothing you call can change data or trigger work.

A good way to explore: get_dashboard for a portfolio overview, list_clients and
list_sites to find things, get_site for one site's health and integrations,
list_reports then get_report to read a generated report's numbers, and
get_site_metrics for a site's collected metrics over a period.

Metrics only exist for periods that have actually been collected — this_month
and last_month are always kept warm, and each report also collects its own
exact date range.
TXT)]
class ClientReporterServer extends Server
{
    protected array $tools = [
        GetDashboardTool::class,
        ListClientsTool::class,
        ListSitesTool::class,
        GetSiteTool::class,
        ListReportsTool::class,
        GetReportTool::class,
        GetSiteMetricsTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
