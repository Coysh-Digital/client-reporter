<?php

declare(strict_types=1);

namespace App\Livewire\Clients;

use App\Enums\ConnectionStatus;
use App\Enums\ReportPeriodStatus;
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

        // Every report across the client's sites, newest first — used both for
        // the report-history list and each site's latest-report summary.
        $reports = Report::query()
            ->whereIn('site_id', $sites->pluck('id')->all())
            ->with('site')
            ->withCount('shares')
            ->orderByDesc('range_end')
            ->orderByDesc('id')
            ->get();

        $latestPerSite = $reports->groupBy('site_id')->map->first();

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
                        'status' => $this->statusFor($latest),
                        'url' => route('reports.show', $latest),
                    ]
                    : null,
            ];
        })->all();

        $reportHistory = $reports->take(20)->map(fn (Report $report): array => [
            'id' => $report->id,
            'site' => $report->site->name,
            'period' => $report->dateRange()->label(),
            'generatedAt' => $report->generated_at,
            'status' => $this->statusFor($report),
            'url' => route('reports.show', $report),
        ])->all();

        return view('livewire.clients.show', [
            'sitesSummary' => $sitesSummary,
            'reportHistory' => $reportHistory,
            'reportsTotal' => $reports->count(),
            'reportsSent' => $reports->filter(fn (Report $r): bool => $this->statusFor($r) === ReportPeriodStatus::Sent)->count(),
        ]);
    }

    private function statusFor(Report $report): ReportPeriodStatus
    {
        if (! $report->isGenerated() && $report->status !== 'final') {
            return ReportPeriodStatus::Draft;
        }

        return ((int) ($report->shares_count ?? 0)) > 0
            ? ReportPeriodStatus::Sent
            : ReportPeriodStatus::Ready;
    }
}
