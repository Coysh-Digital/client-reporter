<?php

declare(strict_types=1);

namespace App\Livewire\Templates;

use App\Models\ReportTemplate;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Report templates')]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('manage-reports');
    }

    public function delete(int $templateId, AuditLogger $audit): void
    {
        $this->authorize('manage-reports');

        $template = ReportTemplate::query()->findOrFail($templateId);
        $audit->log('report_template.deleted', $template, metadata: ['name' => $template->name]);
        $template->delete();
    }

    /**
     * @return Collection<int, ReportTemplate>
     */
    public function templates(): Collection
    {
        return ReportTemplate::query()->orderBy('name')->get();
    }

    public function render(): mixed
    {
        return view('livewire.templates.index', ['templates' => $this->templates()]);
    }
}
