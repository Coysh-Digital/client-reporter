<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Integrations\CollectorRunner;
use App\Integrations\IntegrationRegistry;
use App\Models\Site;
use App\Support\AuditLogger;
use App\Support\DateRange;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The integrations panel on a site page: shows connected services with their
 * health and lets staff connect, collect, manage or disconnect them.
 */
class SitePanel extends Component
{
    public Site $site;

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    #[On('integration-updated')]
    public function refresh(): void
    {
        $this->site->load('integrations');
    }

    public function collectNow(int $connectionId, CollectorRunner $runner): void
    {
        $this->authorize('manage-integrations');

        $connection = $this->site->integrations()->findOrFail($connectionId);
        $runner->collectAll($connection, DateRange::thisMonth());

        session()->flash('panel_status', 'Collection run complete.');
    }

    public function disconnect(int $connectionId, AuditLogger $audit): void
    {
        $this->authorize('manage-integrations');

        $connection = $this->site->integrations()->findOrFail($connectionId);
        $audit->log('integration.disconnected', $connection, metadata: ['integration' => $connection->integration_key]);
        $connection->delete();

        session()->flash('panel_status', 'Service disconnected.');
    }

    public function render(): mixed
    {
        $registry = app(IntegrationRegistry::class);

        return view('livewire.integrations.site-panel', [
            'connections' => $this->site->integrations()->orderBy('name')->get(),
            'available' => $registry->byCategory(),
        ]);
    }
}
