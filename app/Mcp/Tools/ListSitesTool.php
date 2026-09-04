<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesStaffAccess;
use App\Models\Site;
use App\Support\Dashboard\SiteHealthResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List sites (client websites), optionally filtered by client, with their current health status. Read-only.')]
class ListSitesTool extends Tool
{
    use AuthorizesStaffAccess;

    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessStaff($request)) {
            return $denied;
        }

        $clientId = $request->get('client_id');

        $sites = Site::query()
            ->with('client')
            ->when(is_int($clientId), fn ($q) => $q->where('client_id', $clientId))
            ->when($request->get('active_only') === true, fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        $health = app(SiteHealthResolver::class)->forSites($sites);

        return Response::json([
            'count' => $sites->count(),
            'sites' => $sites->map(function (Site $site) use ($health): array {
                $status = $health[$site->id] ?? null;

                return [
                    'id' => $site->id,
                    'name' => $site->name,
                    'url' => $site->url,
                    'host' => $site->host(),
                    'cms_type' => $site->cms_type,
                    'environment' => $site->environment,
                    'is_active' => $site->is_active,
                    'client' => $site->client !== null
                        ? ['id' => $site->client->id, 'name' => $site->client->name]
                        : null,
                    'health' => $status !== null
                        ? ['status' => $status->value, 'label' => $status->label()]
                        : null,
                ];
            })->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()
                ->description('Only return sites belonging to this client id.'),
            'active_only' => $schema->boolean()
                ->description('Only return active sites. Defaults to false (all sites).'),
        ];
    }
}
