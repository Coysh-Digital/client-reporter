<?php

declare(strict_types=1);

namespace App\Livewire\Clients;

use App\Models\Client;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Client $client;

    public function mount(Client $client): void
    {
        $this->client = $client;
    }

    public function render(): mixed
    {
        $this->client->load(['sites' => fn ($q) => $q->orderBy('name')]);

        return view('livewire.clients.show');
    }
}
