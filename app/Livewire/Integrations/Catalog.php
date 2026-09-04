<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Integrations\IntegrationRegistry;
use App\Models\ClientBillingConnection;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\WorkspaceIntegration;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Integrations')]
class Catalog extends Component
{
    public function render(): mixed
    {
        $counts = SiteIntegration::query()
            ->select('integration_key', DB::raw('count(*) as total'))
            ->groupBy('integration_key')
            ->pluck('total', 'integration_key');

        // Where "connect" should take the operator: straight to the one site's
        // connect screen when there is only one, otherwise the site picker.
        $siteCount = Site::query()->count();
        $singleSite = $siteCount === 1 ? Site::query()->first() : null;

        // Workspace-wide connections, keyed by integration key, so a card can
        // show that an integration is already connected for the whole workspace.
        $workspace = WorkspaceIntegration::query()->get()->keyBy('integration_key');

        // Clients mapped to each billing connection (FreeAgent, Xero — the only
        // integrations with no per-site count to fall back on).
        $billingMappedCounts = ClientBillingConnection::query()
            ->select('workspace_integration_id', DB::raw('count(*) as total'))
            ->groupBy('workspace_integration_id')
            ->pluck('total', 'workspace_integration_id');

        return view('livewire.integrations.catalog', [
            'grouped' => app(IntegrationRegistry::class)->byCategory(),
            'counts' => $counts,
            'siteCount' => $siteCount,
            'singleSite' => $singleSite,
            'workspace' => $workspace,
            'billingMappedCounts' => $billingMappedCounts,
        ]);
    }
}
