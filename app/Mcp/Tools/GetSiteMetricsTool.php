<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Integrations\Support\IntegrationCategory;
use App\Mcp\Concerns\AuthorizesStaffAccess;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Reporting\MetricReader;
use App\Support\DateRange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Read the metrics collected for a site over a preset period (visitors, uptime, ecommerce, search, etc.). Read-only. Data exists only for periods that have been collected — prefer this_month or last_month.')]
class GetSiteMetricsTool extends Tool
{
    use AuthorizesStaffAccess;

    private const PERIODS = ['this_month', 'last_month', 'last_week', 'last_7_days', 'last_30_days', 'this_quarter', 'last_quarter'];

    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessStaff($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'site_id' => ['required', 'integer'],
            'period' => ['sometimes', 'string', 'in:'.implode(',', self::PERIODS)],
            'category' => ['sometimes', 'string'],
        ]);

        $site = Site::query()->find($validated['site_id']);

        if ($site === null) {
            return Response::error("No site found with id {$validated['site_id']}.");
        }

        $range = DateRange::fromPreset($validated['period'] ?? 'this_month');
        $reader = app(MetricReader::class);

        $categoryInput = $validated['category'] ?? null;
        $metrics = [];

        if (is_string($categoryInput) && $categoryInput !== '') {
            $category = IntegrationCategory::tryFrom($categoryInput);

            if ($category === null) {
                return Response::error('Unknown category. Use one of: '.implode(', ', array_map(fn ($c) => $c->value, IntegrationCategory::cases())).'.');
            }

            $metrics[$category->value] = $reader->metricsForCategory($site, $category, $range);
        } else {
            foreach ($site->integrations as $connection) {
                /** @var SiteIntegration $connection */
                if (! $connection->status->isLive()) {
                    continue;
                }

                $values = $reader->metrics($site, $connection->integration_key, $range);

                if ($values !== []) {
                    $metrics[$connection->integration_key] = $values;
                }
            }
        }

        return Response::json([
            'site' => ['id' => $site->id, 'name' => $site->name],
            'period' => [
                'preset' => $validated['period'] ?? 'this_month',
                'start' => $range->start->toDateString(),
                'end' => $range->end->toDateString(),
                'label' => $range->label(),
            ],
            'metrics' => $metrics,
            'note' => $metrics === []
                ? 'No metrics for this period. Data is only stored for periods that have been collected (this_month and last_month are kept warm); a report also collects its own exact range.'
                : null,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()
                ->description('The id of the site to read metrics for.')
                ->required(),
            'period' => $schema->string()
                ->enum(self::PERIODS)
                ->description('The period to read. Defaults to this_month. Metrics exist only for collected periods.'),
            'category' => $schema->string()
                ->enum(array_map(fn (IntegrationCategory $c) => $c->value, IntegrationCategory::cases()))
                ->description('Optional: limit to one integration category (e.g. analytics, monitoring, ecommerce). Omit to return every connected integration\'s metrics.'),
        ];
    }
}
