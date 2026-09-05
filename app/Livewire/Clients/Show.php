<?php

declare(strict_types=1);

namespace App\Livewire\Clients;

use App\Enums\ConnectionStatus;
use App\Models\Client;
use App\Models\Report;
use App\Models\Site;
use App\Support\Dashboard\SiteHealthResolver;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    private const HISTORY_LIMIT = 20;

    public Client $client;

    public function mount(Client $client): void
    {
        $this->client = $client;
    }

    public function render(): mixed
    {
        /** @var Collection<int, Site> $sites */
        $sites = $this->client->sites()
            ->orderBy('name')
            ->withCount([
                'reports',
                'integrations as connected_integrations_count' => fn ($q) => $q->whereIn('status', [
                    ConnectionStatus::Connected->value,
                    ConnectionStatus::NeedsAttention->value,
                ]),
            ])
            ->get();

        $health = app(SiteHealthResolver::class)->forSites($sites);

        $siteIds = $sites->pluck('id')->all();

        // Recent reports across the client's sites, newest first, bounded —
        // a long-standing client accumulates hundreds and only the latest few
        // are shown. Each site's own latest report is looked up separately so
        // the summary is right even for a site whose reports are older.
        $reports = Report::query()
            ->whereIn('site_id', $siteIds)
            ->with('site')
            ->withCount('shares')
            ->orderByDesc('range_end')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();

        $latestPerSite = Report::query()
            ->whereIn('site_id', $siteIds)
            ->withCount('shares')
            ->orderByDesc('range_end')
            ->orderByDesc('id')
            ->get()
            ->unique('site_id')
            ->keyBy('site_id');

        $totals = Report::query()
            ->whereIn('site_id', $siteIds)
            ->selectRaw('count(*) as total, sum(case when exists (select 1 from report_shares where report_shares.report_id = reports.id) then 1 else 0 end) as sent')
            ->first();

        $sitesSummary = $sites->map(function (Site $site) use ($health, $latestPerSite): array {
            $latest = $latestPerSite->get($site->id);

            return [
                'site' => $site,
                'health' => $health[$site->id] ?? null,
                'connectedIntegrations' => (int) ($site->connected_integrations_count ?? 0),
                'reportsCount' => (int) ($site->reports_count ?? 0),
                'scheduled' => $site->report_frequency->isScheduled() ? $site->report_frequency->label() : null,
                'latestReport' => $latest !== null
                    ? [
                        'period' => $latest->dateRange()->label(),
                        'status' => $latest->periodStatus(),
                        'url' => route('reports.show', $latest),
                    ]
                    : null,
            ];
        })->all();

        $reportHistory = $reports->map(fn (Report $report): array => [
            'id' => $report->id,
            'site' => $report->site->name,
            'period' => $report->dateRange()->label(),
            'generatedAt' => $report->generated_at,
            'status' => $report->periodStatus(),
            'url' => route('reports.show', $report),
        ])->all();

        return view('livewire.clients.show', [
            'sitesSummary' => $sitesSummary,
            'reportHistory' => $reportHistory,
            'reportsTotal' => (int) ($totals->total ?? 0),
            'reportsSent' => (int) ($totals->sent ?? 0),
        ]);
    }
}
