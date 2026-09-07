<?php

declare(strict_types=1);

namespace App\Livewire\Clients;

use App\Enums\ConnectionStatus;
use App\Enums\SiteHealth;
use App\Models\Client;
use App\Models\Report;
use App\Models\Site;
use App\Support\Dashboard\SiteHealthResolver;
use App\Support\DateRange;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    private const RECENT_REPORTS = 8;

    public Client $client;

    public function mount(Client $client): void
    {
        $this->client = $client;
    }

    public function render(SiteHealthResolver $healthResolver): mixed
    {
        /** @var Collection<int, Site> $sites */
        $sites = $this->client->sites()
            ->orderBy('name')
            ->withCount([
                'reports',
                'integrations as connected_integrations_count' => fn ($q) => $q->where('status', '!=', ConnectionStatus::NotConnected->value),
                'integrations as troubled_integrations_count' => fn ($q) => $q->whereIn('status', ConnectionStatus::troubledValues()),
            ])
            ->get();

        $health = $healthResolver->forSites($sites->where('is_active', true));
        $siteIds = $sites->pluck('id')->all();

        // The latest report per site (one query), so each row is right even
        // for a site whose reports are older than the recent list.
        $latestPerSite = Report::query()
            ->whereIn('site_id', $siteIds)
            ->withCount('shares')
            ->orderByDesc('range_end')
            ->orderByDesc('id')
            ->get()
            ->unique('site_id')
            ->keyBy('site_id');

        $recentReports = Report::query()
            ->whereIn('site_id', $siteIds)
            ->with('site')
            ->withCount('shares')
            ->orderByDesc('range_end')
            ->orderByDesc('id')
            ->limit(self::RECENT_REPORTS)
            ->get();

        $totals = Report::query()
            ->whereIn('site_id', $siteIds)
            ->selectRaw('count(*) as total, sum(case when exists (select 1 from report_shares where report_shares.report_id = reports.id) then 1 else 0 end) as sent')
            ->first();

        $thisMonth = DateRange::thisMonth();
        $reportsThisPeriod = Report::query()
            ->whereIn('site_id', $siteIds)
            ->whereDate('range_start', '>=', $thisMonth->start->toDateString())
            ->count();

        $sitesSummary = $sites->map(function (Site $site) use ($health, $latestPerSite): array {
            $latest = $latestPerSite->get($site->id);

            return [
                'site' => $site,
                'health' => $site->is_active ? ($health[$site->id] ?? null) : null,
                'connectedIntegrations' => (int) ($site->connected_integrations_count ?? 0),
                'troubledIntegrations' => (int) ($site->troubled_integrations_count ?? 0),
                'reportsCount' => (int) ($site->reports_count ?? 0),
                'scheduled' => $site->hasReportSchedule() ? $site->report_frequency?->label() : null,
                'nextReport' => $site->hasReportSchedule() ? $site->report_frequency?->nextGenerationDate() : null,
                'latestReport' => $latest !== null
                    ? [
                        'period' => $latest->dateRange()->label(),
                        'status' => $latest->periodStatus(),
                        'url' => route('reports.show', $latest),
                    ]
                    : null,
            ];
        })->all();

        return view('livewire.clients.show', [
            'sitesSummary' => $sitesSummary,
            'strip' => [
                'sites' => $sites->count(),
                'healthy' => count(array_filter($health, fn (SiteHealth $h): bool => $h === SiteHealth::Healthy)),
                'activeSites' => $sites->where('is_active', true)->count(),
                'troubled' => (int) $sites->sum('troubled_integrations_count'),
                'connected' => (int) $sites->sum('connected_integrations_count'),
                'reportsThisPeriod' => $reportsThisPeriod,
            ],
            'recentReports' => $recentReports,
            'reportsTotal' => (int) ($totals->total ?? 0),
            'reportsSent' => (int) ($totals->sent ?? 0),
            'portalUsers' => $this->client->portalUsers()->orderBy('name')->get(['id', 'name', 'email', 'is_active', 'last_login_at']),
        ])->title($this->client->name);
    }
}
