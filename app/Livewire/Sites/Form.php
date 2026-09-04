<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Models\Client;
use App\Models\Site;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Form extends Component
{
    public ?Site $site = null;

    public ?int $client_id = null;

    public string $name = '';

    public string $url = '';

    public ?string $cms_type = null;

    public string $environment = 'production';

    public string $timezone = 'UTC';

    public bool $is_active = true;

    public function mount(?Site $site = null): void
    {
        $this->authorize('manage-sites');

        if ($site?->exists) {
            $this->site = $site;
            $this->client_id = $site->client_id;
            $this->name = $site->name;
            $this->url = $site->url;
            $this->cms_type = $site->cms_type;
            $this->environment = $site->environment;
            $this->timezone = $site->timezone;
            $this->is_active = $site->is_active;

            return;
        }

        // Pre-select the client when arriving from a client's page.
        $this->client_id = (int) request()->integer('client') ?: null;
    }

    /**
     * Derive a friendly site name from the URL host if the name is still blank.
     */
    public function updatedUrl(): void
    {
        if ($this->name !== '' || $this->url === '') {
            return;
        }

        $host = parse_url(str_starts_with($this->url, 'http') ? $this->url : "https://{$this->url}", PHP_URL_HOST);

        if (is_string($host)) {
            $this->name = (string) preg_replace('/^www\./', '', $host);
        }
    }

    public function save(AuditLogger $audit): mixed
    {
        $this->authorize('manage-sites');

        $validated = $this->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:255'],
            'cms_type' => ['nullable', 'in:wordpress,craft,other'],
            'environment' => ['required', 'string', 'max:50'],
            'timezone' => ['required', 'timezone'],
            'is_active' => ['boolean'],
        ]);

        if ($this->site) {
            $this->site->update($validated);
            $audit->log('site.updated', $this->site);
            $redirect = $this->site;
        } else {
            $redirect = Site::query()->create($validated);
            $audit->log('site.created', $redirect);
        }

        session()->flash('status', $this->site ? 'Site updated.' : 'Site created.');

        return $this->redirectRoute('sites.show', $redirect, navigate: true);
    }

    /**
     * @return Collection<int, Client>
     */
    public function clients(): Collection
    {
        return Client::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<int, string>
     */
    public function timezones(): array
    {
        return timezone_identifiers_list();
    }

    public function render(): mixed
    {
        return view('livewire.sites.form');
    }
}
