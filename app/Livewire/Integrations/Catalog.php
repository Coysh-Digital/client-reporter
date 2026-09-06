<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Models\ClientBillingConnection;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\WorkspaceIntegration;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Integrations')]
class Catalog extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    /** '' (all) or an IntegrationCategory value. */
    #[Url]
    public string $category = '';

    public function setCategory(string $category): void
    {
        $this->category = IntegrationCategory::tryFrom($category) !== null ? $category : '';
    }

    public function render(): mixed
    {
        // Per-integration health across every site: connected vs needing a person.
        $health = SiteIntegration::query()
            ->select('integration_key', DB::raw('count(*) as total'), DB::raw(sprintf(
                "sum(case when status in ('%s') then 1 else 0 end) as troubled",
                implode("','", ConnectionStatus::troubledValues()),
            )))
            ->where('status', '!=', ConnectionStatus::NotConnected->value)
            ->groupBy('integration_key')
            ->get()
            ->keyBy('integration_key');

        // Where "connect" should take the operator: straight to the one site's
        // connect screen when there is only one, otherwise a site picker.
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

        $term = mb_strtolower(trim($this->search));
        $grouped = [];
        foreach (app(IntegrationRegistry::class)->byCategory() as $category => $integrations) {
            if ($this->category !== '' && $this->category !== $category) {
                continue;
            }
            $items = array_values(array_filter($integrations, function ($integration) use ($term): bool {
                if ($term === '') {
                    return true;
                }
                $m = $integration->manifest();

                return str_contains(mb_strtolower($m->name.' '.$m->description), $term);
            }));
            if ($items !== []) {
                $grouped[$category] = $items;
            }
        }

        return view('livewire.integrations.catalog', [
            'grouped' => $grouped,
            'allGrouped' => app(IntegrationRegistry::class)->byCategory(),
            'health' => $health,
            'siteCount' => $siteCount,
            'singleSite' => $singleSite,
            'sites' => $siteCount > 1 && $siteCount <= 200 ? Site::query()->with('client:id,name')->orderBy('name')->get(['id', 'name', 'client_id', 'url']) : collect(),
            'workspace' => $workspace,
            'billingMappedCounts' => $billingMappedCounts,
        ]);
    }
}
