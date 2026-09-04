<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

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
    use WithPagination;

    /** Selectable page sizes for the results-per-page control. */
    public const PER_PAGE_OPTIONS = [15, 30, 50, 100];

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public int $perPage = 15;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Site>
     */
    public function sites(): LengthAwarePaginator
    {
        return Site::query()
            ->with('client')
            ->when($this->search !== '', fn ($query) => $query->where(function ($q): void {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('url', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$this->search}%"));
            }))
            ->orderBy('name')
            ->paginate($this->pageSize());
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
        ]);
    }
}
