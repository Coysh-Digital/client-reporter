<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserRole;
use App\Integrations\IntegrationRegistry;
use App\Models\ClientBillingConnection;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Notifications\IntegrationFailed;
use Illuminate\Support\Facades\Notification;

/**
 * Raises in-app notifications when a connection needs a person to act — its
 * authentication expired, or it was auto-disabled after repeated failures.
 * Notifies the staff who can actually fix it (managers and administrators).
 * Callers only fire this on the transition into a needs-action state, so a
 * connection is never re-announced on every retry.
 */
class IntegrationAlerts
{
    public function connectionNeedsAction(SiteIntegration $connection, string $reason): void
    {
        $connection->loadMissing('site');
        $name = app(IntegrationRegistry::class)->find($connection->integration_key)?->manifest()->name
            ?? $connection->integration_key;

        $this->notify(
            title: $name.' needs attention',
            body: $connection->site !== null ? $reason.' ('.$connection->site->name.')' : $reason,
            url: $connection->site !== null ? route('sites.show', $connection->site_id) : null,
        );
    }

    public function billingNeedsAction(ClientBillingConnection $link, string $reason): void
    {
        $link->loadMissing(['client', 'workspaceIntegration']);
        $name = $link->workspaceIntegration->name;

        $this->notify(
            title: $name.' needs reconnecting',
            body: $link->client !== null ? $reason.' ('.$link->client->name.')' : $reason,
            url: $link->client !== null ? route('clients.show', $link->client_id) : null,
        );
    }

    private function notify(string $title, string $body, ?string $url): void
    {
        $recipients = User::query()
            ->whereIn('role', [UserRole::Manager->value, UserRole::Administrator->value])
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new IntegrationFailed($title, $body, $url));
    }
}
