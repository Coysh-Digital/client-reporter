<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesStaffAccess;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Support\Dashboard\ReportStatusResolver;
use App\Support\Dashboard\SiteHealthResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get one site in detail: its client, health, connected integrations (never credentials) and this period\'s report status. Read-only.')]
class GetSiteTool extends Tool
{
    use AuthorizesStaffAccess;

    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessStaff($request)) {
            return $denied;
        }

        ['site_id' => $siteId] = $request->validate([
            'site_id' => ['required', 'integer'],
        ]);

        $site = Site::query()->with(['client', 'integrations'])->whereKey($siteId)->first();

        if ($site === null) {
            return Response::error("No site found with id {$siteId}.");
        }

        $sites = new Collection([$site->id => $site]);
        $health = app(SiteHealthResolver::class)->forSites($sites)[$site->id] ?? null;
        $reportStatus = app(ReportStatusResolver::class)->forSites($sites)[$site->id] ?? null;

        return Response::json([
            'id' => $site->id,
            'name' => $site->name,
            'url' => $site->url,
            'host' => $site->host(),
            'cms_type' => $site->cms_type,
            'environment' => $site->environment,
            'timezone' => $site->timezone,
            'is_active' => $site->is_active,
            'client' => $site->client !== null
                ? ['id' => $site->client->id, 'name' => $site->client->name]
                : null,
            'health' => $health !== null
                ? ['status' => $health->value, 'label' => $health->label()]
                : null,
            'current_report' => $reportStatus !== null
                ? [
                    'status' => $reportStatus['status']->value,
                    'label' => $reportStatus['status']->label(),
                    'report_id' => $reportStatus['report']?->id,
                ]
                : null,
            'integrations' => $site->integrations->map(fn (SiteIntegration $connection): array => [
                'integration_key' => $connection->integration_key,
                'name' => $connection->name,
                'status' => $connection->status->value,
                'status_label' => $connection->status->label(),
                'last_collected_at' => $connection->last_collected_at?->toIso8601String(),
                'last_error' => $connection->last_error,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()
                ->description('The id of the site to fetch.')
                ->required(),
        ];
    }
}
