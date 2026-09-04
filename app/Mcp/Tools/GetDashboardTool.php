<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\ReportPeriodStatus;
use App\Enums\SiteHealth;
use App\Mcp\Concerns\AuthorizesStaffAccess;
use App\Models\Client;
use App\Models\Site;
use App\Support\Dashboard\ReportStatusResolver;
use App\Support\Dashboard\SiteHealthResolver;
use App\Support\DateRange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('A portfolio overview for a period: client and site totals, the health split across all sites, and how many reports are sent vs still to prepare. Read-only.')]
class GetDashboardTool extends Tool
{
    use AuthorizesStaffAccess;

    private const PERIODS = ['this_month', 'last_month', 'last_week', 'last_30_days', 'this_quarter', 'last_quarter'];

    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessStaff($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'period' => ['sometimes', 'string', 'in:'.implode(',', self::PERIODS)],
        ]);

        $range = DateRange::fromPreset($validated['period'] ?? 'this_month');

        $sites = Site::query()->where('is_active', true)->with('client')->get();
        $health = app(SiteHealthResolver::class)->forSites($sites, $range);
        $reportStatus = app(ReportStatusResolver::class)->forSites($sites, $range);

        $healthSplit = [
            SiteHealth::Healthy->value => 0,
            SiteHealth::NeedsAttention->value => 0,
            SiteHealth::Down->value => 0,
        ];
        foreach ($health as $status) {
            $healthSplit[$status->value]++;
        }

        $reportsSent = 0;
        $reportsToPrepare = 0;
        foreach ($reportStatus as $entry) {
            if ($entry['status'] === ReportPeriodStatus::Sent) {
                $reportsSent++;
            } else {
                $reportsToPrepare++;
            }
        }

        return Response::json([
            'period' => [
                'preset' => $validated['period'] ?? 'this_month',
                'start' => $range->start->toDateString(),
                'end' => $range->end->toDateString(),
                'label' => $range->label(),
            ],
            'totals' => [
                'clients' => Client::query()->where('is_active', true)->count(),
                'sites' => $sites->count(),
                'sites_healthy' => $healthSplit[SiteHealth::Healthy->value],
                'sites_needs_attention' => $healthSplit[SiteHealth::NeedsAttention->value],
                'sites_down' => $healthSplit[SiteHealth::Down->value],
                'reports_sent' => $reportsSent,
                'reports_to_prepare' => $reportsToPrepare,
            ],
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()
                ->enum(self::PERIODS)
                ->description('The period to summarise. Defaults to this_month.'),
        ];
    }
}
