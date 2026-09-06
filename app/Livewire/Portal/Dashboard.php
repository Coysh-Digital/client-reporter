<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Models\Client;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use App\Support\Dashboard\SiteHealthResolver;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What a client sees when they sign in: every published report for their
 * sites, newest first and grouped by year, plus a card per website.
 */
#[Layout('components.layouts.portal')]
#[Title('Your reports')]
class Dashboard extends Component
{
    use WithPagination;

    private const PER_PAGE = 12;

    public Client $client;

    /** Optional site filter (from a website card). */
    #[Url(keep: false)]
    public ?int $site = null;

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $this->client = Client::query()->findOrFail($user->client_id);
    }

    public function updatingSite(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Report>
     */
    public function reports(): LengthAwarePaginator
    {
        return Report::query()
            ->whereHas('site', fn ($q) => $q->where('client_id', $this->client->id))
            ->when($this->site !== null, fn ($q) => $q->where('site_id', $this->site))
            ->where('status', 'final')
            ->whereNotNull('generated_at')
            ->with('site')
            ->orderByDesc('range_end')
            ->orderByDesc('generated_at')
            ->paginate(self::PER_PAGE);
    }

    public function render(SiteHealthResolver $health): mixed
    {
        /** @var Collection<int, Site> $sites */
        $sites = $this->client->sites()->where('is_active', true)->orderBy('name')->get();

        $latest = Report::query()
            ->whereIn('site_id', $sites->pluck('id')->all())
            ->where('status', 'final')
            ->whereNotNull('generated_at')
            ->orderByDesc('range_end')
            ->orderByDesc('generated_at')
            ->get()
            ->unique('site_id')
            ->keyBy('site_id');

        $reports = $this->reports();

        return view('livewire.portal.dashboard', [
            'reports' => $reports,
            'byYear' => $reports->getCollection()->groupBy(fn (Report $r): string => $r->dateRange()->end->format('Y')),
            'sites' => $sites,
            'health' => $health->forSites($sites),
            'latest' => $latest,
        ]);
    }
}
