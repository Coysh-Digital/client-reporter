<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Client;
use App\Models\Report;
use App\Models\Site;
use Livewire\Component;

/**
 * Global ⌘K command palette. Rendered once in the admin layout; searches
 * clients, sites and reports and links straight to them. All viewers here have
 * already passed the access-admin gate, so results need no further filtering.
 */
class CommandPalette extends Component
{
    public string $q = '';

    /**
     * @return array<int, array{group: string, items: array<int, array{label: string, sub: string, url: string}>}>
     */
    public function getResultsProperty(): array
    {
        $term = trim($this->q);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.$term.'%';

        $clients = Client::query()
            ->where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('company', 'like', $like)
                ->orWhere('contact_name', 'like', $like)
                ->orWhere('contact_email', 'like', $like))
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Client $c): array => [
                'label' => $c->name,
                'sub' => $c->company ?: 'Client',
                'url' => route('clients.show', $c),
            ]);

        $sites = Site::query()
            ->where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('url', 'like', $like))
            ->with('client')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Site $s): array => [
                'label' => $s->name,
                'sub' => $s->url ?: 'Site',
                'url' => route('sites.show', $s),
            ]);

        $reports = Report::query()
            ->where('title', 'like', $like)
            ->with('site.client')
            ->latest('range_start')
            ->limit(5)
            ->get()
            ->map(fn (Report $r): array => [
                'label' => $r->title,
                'sub' => $r->site->name,
                'url' => route('reports.show', $r),
            ]);

        return collect([
            ['group' => 'Clients', 'items' => $clients],
            ['group' => 'Sites', 'items' => $sites],
            ['group' => 'Reports', 'items' => $reports],
        ])
            ->filter(fn (array $g): bool => $g['items']->isNotEmpty())
            ->map(fn (array $g): array => ['group' => $g['group'], 'items' => $g['items']->all()])
            ->values()
            ->all();
    }

    public function render(): mixed
    {
        return view('livewire.command-palette');
    }
}
