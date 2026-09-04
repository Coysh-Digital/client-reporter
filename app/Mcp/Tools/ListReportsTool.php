<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesStaffAccess;
use App\Models\Report;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List reports, optionally filtered by site, client or status, newest first. Read-only.')]
class ListReportsTool extends Tool
{
    use AuthorizesStaffAccess;

    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessStaff($request)) {
            return $denied;
        }

        $siteId = $request->get('site_id');
        $clientId = $request->get('client_id');
        $status = $request->get('status');
        $limit = is_int($request->get('limit')) ? max(1, min(100, $request->get('limit'))) : 25;

        $reports = Report::query()
            ->with('site.client')
            ->when(is_int($siteId), fn ($q) => $q->where('site_id', $siteId))
            ->when(is_int($clientId), fn ($q) => $q->whereHas('site', fn ($q) => $q->where('client_id', $clientId)))
            ->when(is_string($status) && $status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('range_end')
            ->limit($limit)
            ->get();

        return Response::json([
            'count' => $reports->count(),
            'reports' => $reports->map(fn (Report $report): array => [
                'id' => $report->id,
                'title' => $report->title,
                'status' => $report->status,
                'is_generated' => $report->isGenerated(),
                'generated_at' => $report->generated_at?->toIso8601String(),
                'compare_previous' => $report->compare_previous,
                'range' => [
                    'start' => $report->range_start->toDateString(),
                    'end' => $report->range_end->toDateString(),
                ],
                'site' => $report->site !== null
                    ? ['id' => $report->site->id, 'name' => $report->site->name]
                    : null,
                'client' => $report->site?->client !== null
                    ? ['id' => $report->site->client->id, 'name' => $report->site->client->name]
                    : null,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Only reports for this site id.'),
            'client_id' => $schema->integer()->description('Only reports for sites belonging to this client id.'),
            'status' => $schema->string()->enum(['draft', 'final'])->description('Filter by report status.'),
            'limit' => $schema->integer()->description('Maximum number of reports to return (1–100, default 25).'),
        ];
    }
}
