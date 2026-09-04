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

#[Description('Get a generated report\'s full contents: every block\'s resolved data (visitor numbers, uptime, ecommerce, etc.) from its frozen snapshot. Read-only.')]
class GetReportTool extends Tool
{
    use AuthorizesStaffAccess;

    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessStaff($request)) {
            return $denied;
        }

        ['report_id' => $reportId] = $request->validate([
            'report_id' => ['required', 'integer'],
        ]);

        $report = Report::query()->with(['site.client', 'latestRender'])->find($reportId);

        if ($report === null) {
            return Response::error("No report found with id {$reportId}.");
        }

        $render = $report->latestRender;

        if (! $report->isGenerated() || $render === null) {
            return Response::error('This report has not been generated yet, so it has no data. Generate it in the app first.');
        }

        $blocks = [];
        foreach ($render->data as $blockId => $entry) {
            $blocks[] = [
                'id' => $blockId,
                'type' => $entry['type'] ?? null,
                'heading' => $entry['heading'] ?? null,
                'commentary' => $entry['commentary'] ?? null,
                'data' => $entry['data'] ?? [],
            ];
        }

        return Response::json([
            'report' => [
                'id' => $report->id,
                'title' => $report->title,
                'status' => $report->status,
                'generated_at' => $report->generated_at?->toIso8601String(),
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
            ],
            'meta' => $render->meta,
            'blocks' => $blocks,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'report_id' => $schema->integer()
                ->description('The id of the report to fetch. It must already be generated.')
                ->required(),
        ];
    }
}
