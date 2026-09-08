<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Enums\ReportFrequency;
use App\Models\Report;
use App\Models\ReportDelivery;
use App\Models\Site;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Scheduled reports')]
class Scheduled extends Component
{
    /**
     * Every active site on a reporting schedule, with its next generation date,
     * whether it auto-sends, its most recent auto-generated report and the last
     * time a report actually went out — soonest next-run first.
     *
     * @return array<int, array{site: Site, frequency: string, template: ?string, autoSend: bool, next: ?CarbonImmutable, lastReport: ?Report, lastSent: ?Carbon}>
     */
    public function scheduledSites(): array
    {
        $rows = Site::query()
            ->with(['client:id,name', 'reportTemplate:id,name'])
            ->where('is_active', true)
            ->whereNotNull('report_frequency')
            ->where('report_frequency', '!=', ReportFrequency::None->value)
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site): array => [
                'site' => $site,
                'frequency' => $site->report_frequency?->label() ?? '',
                'template' => $site->reportTemplate?->name,
                'autoSend' => $site->autoSends(),
                'next' => $site->report_frequency?->nextGenerationDate(),
                'lastReport' => $site->reports()->where('scheduled', true)->latest('id')->first(),
                'lastSent' => ReportDelivery::query()
                    ->where('succeeded', true)
                    ->whereHas('report', fn ($query) => $query->where('site_id', $site->id))
                    ->latest('id')
                    ->first()?->created_at,
            ])
            ->all();

        usort($rows, fn (array $a, array $b): int => ($a['next']?->getTimestamp() ?? PHP_INT_MAX) <=> ($b['next']?->getTimestamp() ?? PHP_INT_MAX));

        return $rows;
    }

    public function render(): mixed
    {
        return view('livewire.reports.scheduled', [
            'sites' => $this->scheduledSites(),
        ]);
    }
}
