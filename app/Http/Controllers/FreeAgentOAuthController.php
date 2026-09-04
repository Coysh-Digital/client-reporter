<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConnectionStatus;
use App\Models\WorkspaceIntegration;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

/**
 * OAuth for the agency's own FreeAgent account. FreeAgent is workspace-only —
 * there is no per-site connection — so this only ever targets a
 * WorkspaceIntegration, unlike the Google controller which handles both.
 */
class FreeAgentOAuthController
{
    public function redirect(WorkspaceIntegration $workspace): RedirectResponse
    {
        Gate::authorize('manage-integrations');

        if (! $this->isConfigured()) {
            return redirect()->route('integrations.workspace.edit', $workspace)
                ->with('status', 'FreeAgent OAuth is not configured on this installation yet. Add FREEAGENT_CLIENT_ID and FREEAGENT_CLIENT_SECRET.');
        }

        $params = http_build_query([
            'client_id' => config('services.freeagent.client_id'),
            'redirect_uri' => route('integrations.freeagent.callback'),
            'response_type' => 'code',
            'state' => Crypt::encryptString((string) $workspace->id),
        ]);

        return redirect('https://api.freeagent.com/v2/approve_app?'.$params);
    }

    public function callback(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-integrations');

        try {
            $workspaceId = (int) Crypt::decryptString((string) $request->query('state'));
        } catch (\Throwable) {
            abort(403, 'Invalid OAuth state.');
        }

        $workspace = WorkspaceIntegration::query()->findOrFail($workspaceId);

        if ($request->query('error') || ! $request->filled('code')) {
            return redirect()->route('integrations.workspace.edit', $workspace)
                ->with('status', 'FreeAgent connection was cancelled.');
        }

        $response = Http::asForm()->post('https://api.freeagent.com/v2/token_endpoint', [
            'code' => $request->query('code'),
            'client_id' => config('services.freeagent.client_id'),
            'client_secret' => config('services.freeagent.client_secret'),
            'redirect_uri' => route('integrations.freeagent.callback'),
            'grant_type' => 'authorization_code',
        ]);

        $refreshToken = $response->json('refresh_token');

        if (! $response->successful() || ! is_string($refreshToken)) {
            $workspace->update(['status' => ConnectionStatus::Error, 'last_error' => 'FreeAgent did not return a refresh token. Please try again.']);

            return redirect()->route('integrations.workspace.edit', $workspace)
                ->with('status', 'Could not complete the FreeAgent connection.');
        }

        $credentials = $workspace->credentials ?? [];
        $credentials['refresh_token'] = $refreshToken;

        $workspace->update([
            'credentials' => $credentials,
            'status' => ConnectionStatus::Connected,
            'last_connected_at' => now(),
            'last_error' => null,
        ]);

        $audit->log('integration.workspace_connected', $workspace, metadata: ['integration' => 'freeagent', 'via' => 'oauth']);

        return redirect()->route('integrations.workspace.edit', $workspace)
            ->with('status', 'FreeAgent connected. Now find contacts to map.');
    }

    private function isConfigured(): bool
    {
        return ! empty(config('services.freeagent.client_id')) && ! empty(config('services.freeagent.client_secret'));
    }
}
