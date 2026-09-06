<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Livewire\Concerns\SortsAndFilters;
use App\Models\Client;
use App\Models\Site;
use App\Support\Dashboard\SiteHealthResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Sites')]
class Index extends Component
{
    use SortsAndFilters;
    use WithPagination;

    /** Selectable page sizes for the results-per-page control. */
    public const PER_PAGE_OPTIONS = [15, 30, 50, 100];

    #[Url]
    public int $perPage = 15;

    /** all | active | inactive */
    #[Url]
    public string $status = 'all';

    #[Url]
    public ?int $client = null;

    #[Url]
    public string $cms = '';

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingClient(): void
    {
        $this->resetPage();
    }

    public function updatingCms(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all';
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    protected function sortable(): array
    {
        return ['name' => 'name', 'client' => 'client.name', 'cms' => 'cms_type', 'status' => 'is_active'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['name', 'url', 'client.name'];
    }

    /**
     * @return LengthAwarePaginator<int, Site>
     */
    public function sites(): LengthAwarePaginator
    {
        $query = Site::query()
            ->with('client')
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($this->client !== null, fn ($query) => $query->where('client_id', $this->client))
            ->when($this->cms !== '', fn ($query) => $query->where('cms_type', $this->cms));

        return $this->applySortAndSearch($query)->paginate($this->pageSize());
    }

    /**
     * The validated results-per-page, guarding the URL-bound value against
     * anything outside the offered options.
     */
    private function pageSize(): int
    {
        return in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : 15;
    }

    public function render(SiteHealthResolver $resolver): mixed
    {
        $sites = $this->sites();
        /** @var Collection<int, Site> $active */
        $active = collect($sites->items())->filter(fn (Site $s): bool => $s->is_active)->values();

        return view('livewire.sites.index', [
            'sites' => $sites,
            'health' => $resolver->forSites($active),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'cmsTypes' => Site::query()->whereNotNull('cms_type')->distinct()->orderBy('cms_type')->pluck('cms_type')->all(),
        ]);
    }
}
