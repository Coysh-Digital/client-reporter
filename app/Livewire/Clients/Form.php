<?php

declare(strict_types=1);

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Support\AuditLogger;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Form extends Component
{
    public ?Client $client = null;

    public string $name = '';

    public string $contact_name = '';

    public string $contact_email = '';

    public string $company = '';

    public string $notes = '';

    public bool $is_active = true;

    public function mount(?Client $client = null): void
    {
        $this->authorize('manage-clients');

        if ($client?->exists) {
            $this->client = $client;
            $this->name = $client->name;
            $this->contact_name = (string) $client->contact_name;
            $this->contact_email = (string) $client->contact_email;
            $this->company = (string) $client->company;
            $this->notes = (string) $client->notes;
            $this->is_active = $client->is_active;
        }
    }

    public function save(AuditLogger $audit): mixed
    {
        $this->authorize('manage-clients');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ]);

        if ($this->client) {
            $this->client->update($validated);
            $audit->log('client.updated', $this->client);
            $redirect = $this->client;
        } else {
            $redirect = Client::query()->create($validated);
            $audit->log('client.created', $redirect);
        }

        session()->flash('status', $this->client ? 'Client updated.' : 'Client created.');

        return $this->redirectRoute('clients.show', $redirect, navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.clients.form');
    }
}
