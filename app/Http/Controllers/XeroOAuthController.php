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
 * OAuth for the agency's own Xero account. Xero is workspace-only — there is
 * no per-site connection — so this only ever targets a WorkspaceIntegration.
 */
class XeroOAuthController
{
    private const SCOPE = 'offline_access accounting.contacts.read accounting.transactions.read';

    public function redirect(WorkspaceIntegration $workspace): RedirectResponse
    {
        Gate::authorize('manage-integrations');

        if (! $this->isConfigured()) {
            return redirect()->route('integrations.workspace.edit', $workspace)
                ->with('status', 'Xero OAuth is not configured on this installation yet. Add XERO_CLIENT_ID and XERO_CLIENT_SECRET.');
        }

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.xero.client_id'),
            'redirect_uri' => route('integrations.xero.callback'),
            'scope' => self::SCOPE,
            'state' => Crypt::encryptString((string) $workspace->id),
        ]);

        return redirect('https://login.xero.com/identity/connect/authorize?'.$params);
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
                ->with('status', 'Xero connection was cancelled.');
        }

        $response = Http::asForm()
            ->withBasicAuth((string) config('services.xero.client_id'), (string) config('services.xero.client_secret'))
            ->post('https://identity.xero.com/connect/token', [
                'code' => $request->query('code'),
                'redirect_uri' => route('integrations.xero.callback'),
                'grant_type' => 'authorization_code',
            ]);

        $refreshToken = $response->json('refresh_token');

        if (! $response->successful() || ! is_string($refreshToken)) {
            $workspace->update(['status' => ConnectionStatus::Error, 'last_error' => 'Xero did not return a refresh token. Please try again.']);

            return redirect()->route('integrations.workspace.edit', $workspace)
                ->with('status', 'Could not complete the Xero connection.');
        }

        $credentials = $workspace->credentials ?? [];
        $credentials['refresh_token'] = $refreshToken;

        $workspace->update([
            'credentials' => $credentials,
            'status' => ConnectionStatus::Connected,
            'last_connected_at' => now(),
            'last_error' => null,
        ]);

        $audit->log('integration.workspace_connected', $workspace, metadata: ['integration' => 'xero', 'via' => 'oauth']);

        return redirect()->route('integrations.workspace.edit', $workspace)
            ->with('status', 'Xero connected. Now find contacts to map.');
    }

    private function isConfigured(): bool
    {
        return ! empty(config('services.xero.client_id')) && ! empty(config('services.xero.client_secret'));
    }
}
