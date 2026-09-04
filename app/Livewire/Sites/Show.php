<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Models\Site;
use App\Support\AuditLogger;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Site $site;

    public function mount(Site $site): void
    {
        $this->site = $site->load('client');
    }

    public function delete(AuditLogger $audit): mixed
    {
        $this->authorize('manage-sites');

        $client = $this->site->client;
        $audit->log('site.deleted', $this->site, metadata: ['name' => $this->site->name]);
        $this->site->delete();

        session()->flash('status', 'Site deleted.');

        return $this->redirectRoute('clients.show', $client, navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.sites.show');
    }
}
