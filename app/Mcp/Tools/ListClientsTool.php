<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesStaffAccess;
use App\Models\Client;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the agency\'s clients, with their site counts and contact details. Read-only.')]
class ListClientsTool extends Tool
{
    use AuthorizesStaffAccess;

    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessStaff($request)) {
            return $denied;
        }

        $search = $request->get('search');

        $clients = Client::query()
            ->when($request->get('active_only') === true, fn ($q) => $q->where('is_active', true))
            ->when(is_string($search) && $search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%");
                });
            })
            ->withCount('sites')
            ->orderBy('name')
            ->get();

        return Response::json([
            'count' => $clients->count(),
            'clients' => $clients->map(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'company' => $client->company,
                'contact_name' => $client->contact_name,
                'contact_email' => $client->contact_email,
                'is_active' => $client->is_active,
                'sites_count' => $client->sites_count,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'active_only' => $schema->boolean()
                ->description('Only return active clients. Defaults to false (all clients).'),
            'search' => $schema->string()
                ->description('Optional case-insensitive filter matched against the client name or company.'),
        ];
    }
}
