<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Enums\ConnectionStatus;
use App\Integrations\CollectionSchedule;
use App\Integrations\Contracts\Integration;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\WorkspaceIntegration;
use App\Support\AuditLogger;
use App\Support\Dashboard\SiteHealthResolver;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    private const RECENT_REPORTS = 5;

    public Site $site;

    public function mount(Site $site): void
    {
        $this->site = $site->load('client');
    }

    #[On('integration-updated')]
    public function refresh(): void
    {
        // The health strip and counts depend on the panel's connections.
    }

    public function delete(AuditLogger $audit): mixed
    {
        $this->authorize('manage-sites');

        $client = $this->site->client;
        $audit->log('site.deleted', $this->site, metadata: ['name' => $this->site->name]);
        $this->site->delete();

        session()->flash('status', 'Site deleted.');

        return $this->redirectRoute('clients.show', $client, navigate: true);
    }

    public function render(SiteHealthResolver $health, CollectionSchedule $schedule, IntegrationRegistry $registry): mixed
    {
        /** @var Collection<int, SiteIntegration> $connections */
        $connections = $this->site->integrations()->get(['id', 'integration_key', 'status', 'last_collected_at', 'last_attempted_at']);

        $live = $connections->filter(fn (SiteIntegration $c): bool => $c->status->isLive());
        $nextDue = $live->map(fn (SiteIntegration $c) => $schedule->nextDueAt($c))->filter()->min();

        return view('livewire.sites.show', [
            'health' => $health->for($this->site),
            'summary' => [
                'connected' => $connections->filter(fn (SiteIntegration $c): bool => $c->status !== ConnectionStatus::NotConnected)->count(),
                'attention' => $connections->filter(fn (SiteIntegration $c): bool => $c->status->needsAttention())->count(),
                'lastCollected' => $connections->pluck('last_collected_at')->filter()->max(),
                'nextDue' => $nextDue,
            ],
            'available' => $this->availableIntegrations($registry, $connections),
            'recentReports' => $this->site->reports()->latest()->take(self::RECENT_REPORTS)->get(),
            'reportCount' => $this->site->reports()->count(),
            'schedule' => $this->site->hasReportSchedule()
                ? ['frequency' => $this->site->report_frequency->label(), 'template' => $this->site->reportTemplate()->value('name')]
                : null,
        ])->title($this->site->name);
    }

    /**
     * Integrations that can still be connected on this site, grouped by
     * category: not already connected here, not connected once for the whole
     * workspace, and not workspace-only (billing).
     *
     * @param  Collection<int, SiteIntegration>  $connections
     * @return array<string, array{label: string, items: array<int, Integration>}>
     */
    private function availableIntegrations(IntegrationRegistry $registry, Collection $connections): array
    {
        $hidden = $connections->pluck('integration_key')
            ->merge(WorkspaceIntegration::query()->pluck('integration_key'))
            ->all();

        $available = [];
        foreach ($registry->byCategory() as $category => $items) {
            $items = array_values(array_filter(
                $items,
                fn (Integration $i): bool => ! in_array($i->key(), $hidden, true) && ! $i->onlyWorkspaceScope(),
            ));
            if ($items !== []) {
                $available[$category] = ['label' => IntegrationCategory::from($category)->label(), 'items' => $items];
            }
        }

        return $available;
    }
}
