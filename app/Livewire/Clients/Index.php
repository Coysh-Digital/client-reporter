<?php

declare(strict_types=1);

namespace App\Livewire\Clients;

use App\Enums\SiteHealth;
use App\Models\Client;
use App\Models\Site;
use App\Support\AuditLogger;
use App\Support\Dashboard\SiteHealthResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Clients')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** all | active | inactive */
    #[Url]
    public string $status = 'all';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all';
        $this->resetPage();
    }

    public function delete(int $clientId, AuditLogger $audit): void
    {
        $this->authorize('manage-clients');

        $client = Client::query()->findOrFail($clientId);
        $audit->log('client.deleted', $client, metadata: ['name' => $client->name]);
        $client->delete();
    }

    /**
     * @return LengthAwarePaginator<int, Client>
     */
    public function clients(): LengthAwarePaginator
    {
        return Client::query()
            ->withCount('sites')
            ->when($this->search !== '', fn ($query) => $query->where(function ($q): void {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('contact_name', 'like', "%{$this->search}%")
                    ->orWhere('contact_email', 'like', "%{$this->search}%");
            }))
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * Worst-case health per client across its active sites, for the current page.
     *
     * @param  LengthAwarePaginator<int, Client>  $clients
     * @return array<int, SiteHealth>
     */
    private function healthByClient(LengthAwarePaginator $clients, SiteHealthResolver $resolver): array
    {
        $clientIds = collect($clients->items())->pluck('id')->all();

        if ($clientIds === []) {
            return [];
        }

        /** @var Collection<int, Site> $sites */
        $sites = Site::query()
            ->whereIn('client_id', $clientIds)
            ->where('is_active', true)
            ->get(['id', 'client_id']);

        $health = $resolver->forSites($sites);

        $byClient = [];
        foreach ($sites as $site) {
            $siteHealth = $health[$site->id] ?? SiteHealth::Healthy;
            $current = $byClient[$site->client_id] ?? null;
            if ($current === null || $siteHealth->severity() > $current->severity()) {
                $byClient[$site->client_id] = $siteHealth;
            }
        }

        return $byClient;
    }

    public function render(SiteHealthResolver $resolver): mixed
    {
        $clients = $this->clients();

        return view('livewire.clients.index', [
            'clients' => $clients,
            'healthByClient' => $this->healthByClient($clients, $resolver),
        ]);
    }
}
