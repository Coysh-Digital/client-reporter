<?php

declare(strict_types=1);

namespace App\Livewire\Templates;

use App\Livewire\Concerns\SortsAndFilters;
use App\Models\ReportTemplate;
use App\Support\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Report templates')]
class Index extends Component
{
    use SortsAndFilters;
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('manage-reports');
    }

    /**
     * @return array<string, string>
     */
    protected function sortable(): array
    {
        return ['name' => 'name', 'sites' => 'sites_count', 'updated' => 'updated_at'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['name', 'description'];
    }

    /**
     * Clone a template so an agency can branch a house style per client.
     */
    public function duplicate(int $templateId, AuditLogger $audit): void
    {
        $this->authorize('manage-reports');

        $template = ReportTemplate::query()->findOrFail($templateId);
        $copy = ReportTemplate::query()->create([
            'name' => $template->name.' (copy)',
            'description' => $template->description,
            'blocks' => $template->blocks,
        ]);

        $audit->log('report_template.duplicated', $copy, metadata: ['from' => $template->id]);
        $this->dispatch('toast', message: 'Template duplicated.', type: 'ok');
    }

    public function delete(int $templateId, AuditLogger $audit): void
    {
        $this->authorize('manage-reports');

        $template = ReportTemplate::query()->findOrFail($templateId);
        $audit->log('report_template.deleted', $template, metadata: ['name' => $template->name]);
        $template->delete();
    }

    /**
     * @return LengthAwarePaginator<int, ReportTemplate>
     */
    public function templates(): LengthAwarePaginator
    {
        return $this->applySortAndSearch(ReportTemplate::query()->withCount('sites'))->paginate(20);
    }

    public function render(): mixed
    {
        return view('livewire.templates.index', ['templates' => $this->templates()]);
    }
}
