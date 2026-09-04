<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Report;
use App\Support\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Reports')]
class Index extends Component
{
    use WithPagination;

    /** all | draft | final */
    #[Url]
    public string $status = 'all';

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, ['all', 'draft', 'final'], true) ? $status : 'all';
        $this->resetPage();
    }

    public function delete(int $reportId, AuditLogger $audit): void
    {
        $this->authorize('manage-reports');

        $report = Report::query()->findOrFail($reportId);
        $audit->log('report.deleted', $report, metadata: ['title' => $report->title]);
        $report->delete();
    }

    /**
     * @return LengthAwarePaginator<int, Report>
     */
    public function reports(): LengthAwarePaginator
    {
        return Report::query()
            ->with('site.client')
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->latest()
            ->paginate(15);
    }

    public function render(): mixed
    {
        return view('livewire.reports.index', ['reports' => $this->reports()]);
    }
}
