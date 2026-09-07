<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Enums\ReportFrequency;
use App\Models\Report;
use App\Models\Site;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Scheduled reports')]
class Scheduled extends Component
{
    /**
     * Every active site on a reporting schedule, with its next generation date
     * and most recent auto-generated report, soonest first.
     *
     * @return array<int, array{site: Site, frequency: string, template: ?string, next: ?CarbonImmutable, lastReport: ?Report}>
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
                'next' => $site->report_frequency?->nextGenerationDate(),
                'lastReport' => $site->reports()->where('scheduled', true)->latest('id')->first(),
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
