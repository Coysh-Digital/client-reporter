<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConnectionStatus;
use App\Integrations\Support\GoogleOAuth;
use App\Models\SiteIntegration;
use App\Models\WorkspaceIntegration;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

/**
 * Google OAuth for the Google integrations (Analytics, Search Console). Requests
 * offline access so a refresh token is stored (encrypted) on the connection;
 * access tokens are fetched on demand during collection. The scope requested
 * depends on which Google integration is being connected. Works for both a
 * per-site connection and a workspace-wide one — the OAuth `state` carries which
 * kind and its id so the callback knows where to store the refresh token.
 */
class GoogleOAuthController
{
    public function redirect(SiteIntegration $connection): RedirectResponse
    {
        if (! GoogleOAuth::isConfigured()) {
            return redirect()->route('integrations.edit', $connection)
                ->with('status', 'Google OAuth is not configured on this installation yet. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.');
        }

        return $this->authorize($connection->integration_key, 'site:'.$connection->id);
    }

    public function redirectWorkspace(WorkspaceIntegration $workspace): RedirectResponse
    {
        if (! GoogleOAuth::isConfigured()) {
            return redirect()->route('integrations.workspace.edit', $workspace)
                ->with('status', 'Google OAuth is not configured on this installation yet. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.');
        }

        return $this->authorize($workspace->integration_key, 'workspace:'.$workspace->id);
    }

    private function authorize(string $integrationKey, string $state): RedirectResponse
    {
        Gate::authorize('manage-integrations');

        $scope = GoogleOAuth::scopeFor($integrationKey);
        abort_if($scope === null, 404);

        $params = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('integrations.google.callback'),
            'response_type' => 'code',
            'scope' => $scope,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => Crypt::encryptString($state),
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.$params);
    }

    public function callback(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-integrations');

        try {
            [$type, $id] = explode(':', Crypt::decryptString((string) $request->query('state')), 2);
        } catch (\Throwable) {
            abort(403, 'Invalid OAuth state.');
        }

        return $type === 'workspace'
            ? $this->handleWorkspaceCallback($request, $audit, (int) $id)
            : $this->handleSiteCallback($request, $audit, (int) $id);
    }

    private function handleSiteCallback(Request $request, AuditLogger $audit, int $connectionId): RedirectResponse
    {
        $connection = SiteIntegration::query()->findOrFail($connectionId);

        if ($request->query('error') || ! $request->filled('code')) {
            return redirect()->route('integrations.edit', $connection)
                ->with('status', 'Google connection was cancelled.');
        }

        $refreshToken = $this->exchangeCode((string) $request->query('code'));

        if ($refreshToken === null) {
            $connection->update(['status' => ConnectionStatus::Error, 'last_error' => 'Google did not return a refresh token. Try again and grant offline access.']);

            return redirect()->route('integrations.edit', $connection)
                ->with('status', 'Could not complete the Google connection.');
        }

        $credentials = $connection->credentials ?? [];
        $credentials['refresh_token'] = $refreshToken;

        $connection->update([
            'credentials' => $credentials,
            'status' => ConnectionStatus::Connected,
            'last_connected_at' => now(),
            'last_error' => null,
        ]);

        $audit->log('integration.connected', $connection, metadata: ['integration' => $connection->integration_key, 'via' => 'oauth']);

        $name = $connection->integration()?->manifest()->name ?? 'Google';

        return redirect()->route('sites.show', $connection->site_id)
            ->with('status', $name.' connected.');
    }

    private function handleWorkspaceCallback(Request $request, AuditLogger $audit, int $workspaceId): RedirectResponse
    {
        $workspace = WorkspaceIntegration::query()->findOrFail($workspaceId);

        if ($request->query('error') || ! $request->filled('code')) {
            return redirect()->route('integrations.workspace.edit', $workspace)
                ->with('status', 'Google connection was cancelled.');
        }

        $refreshToken = $this->exchangeCode((string) $request->query('code'));

        if ($refreshToken === null) {
            $workspace->update(['status' => ConnectionStatus::Error, 'last_error' => 'Google did not return a refresh token. Try again and grant offline access.']);

            return redirect()->route('integrations.workspace.edit', $workspace)
                ->with('status', 'Could not complete the Google connection.');
        }

        $credentials = $workspace->credentials ?? [];
        $credentials['refresh_token'] = $refreshToken;

        $workspace->update([
            'credentials' => $credentials,
            'status' => ConnectionStatus::Connected,
            'last_connected_at' => now(),
            'last_error' => null,
        ]);

        $audit->log('integration.workspace_connected', $workspace, metadata: ['integration' => $workspace->integration_key, 'via' => 'oauth']);

        $name = $workspace->integration()?->manifest()->name ?? 'Google';

        return redirect()->route('integrations.workspace.edit', $workspace)
            ->with('status', $name.' connected. Now find sites to map.');
    }

    private function exchangeCode(string $code): ?string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => route('integrations.google.callback'),
            'grant_type' => 'authorization_code',
        ]);

        $refreshToken = $response->json('refresh_token');

        return $response->successful() && is_string($refreshToken) ? $refreshToken : null;
    }
}
