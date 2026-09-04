<?php

declare(strict_types=1);

use App\Mcp\Servers\ClientReporterServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP servers
|--------------------------------------------------------------------------
|
| The Client Reporter MCP server gives an AI assistant read-only access to
| your data. It is exposed two ways:
|
| - Local (stdio): run `php artisan mcp:start client-reporter`. Point a local
|   AI client (Claude Desktop/Code) at that command. No network, no auth —
|   whoever runs it already has server access.
| - Web (HTTP): a POST endpoint at /mcp, protected by a Sanctum token that
|   carries the `mcp:read` ability. Mint one with
|   `php artisan client-reporter:mcp-token you@example.com`.
|
*/

Mcp::local('client-reporter', ClientReporterServer::class);

Mcp::web('mcp', ClientReporterServer::class)
    ->middleware(['auth:sanctum', 'ability:mcp:read']);
