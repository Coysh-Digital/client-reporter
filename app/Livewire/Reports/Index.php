<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Livewire\Concerns\SortsAndFilters;
use App\Models\Client;
use App\Models\Report;
use App\Models\Site;
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
    use SortsAndFilters;
    use WithPagination;

    /** all | draft | final */
    #[Url]
    public string $status = 'all';

    #[Url]
    public ?int $site = null;

    #[Url]
    public ?int $client = null;

    public function updatingSite(): void
    {
        $this->resetPage();
    }

    public function updatingClient(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    protected function sortable(): array
    {
        return ['period' => 'range_start', 'title' => 'title', 'site' => 'site.name', 'status' => 'status', 'generated' => 'generated_at'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function defaultSort(): array
    {
        return ['period', 'desc'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['title', 'site.name'];
    }

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
        $query = Report::query()
            ->with('site.client')
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->site !== null, fn ($query) => $query->where('site_id', $this->site))
            ->when($this->client !== null, fn ($query) => $query->whereHas('site', fn ($s) => $s->where('client_id', $this->client)));

        return $this->applySortAndSearch($query)->orderByDesc('id')->paginate(15);
    }

    public function render(): mixed
    {
        return view('livewire.reports.index', [
            'reports' => $this->reports(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->when($this->client !== null, fn ($q) => $q->where('client_id', $this->client))->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
