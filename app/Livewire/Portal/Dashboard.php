<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Models\Client;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.portal')]
#[Title('Your reports')]
class Dashboard extends Component
{
    public Client $client;

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $this->client = Client::query()->findOrFail($user->client_id);
    }

    /**
     * @return Collection<int, Report>
     */
    public function reports(): Collection
    {
        return Report::query()
            ->whereHas('site', fn ($q) => $q->where('client_id', $this->client->id))
            ->where('status', 'final')
            ->with('site')
            ->latest('generated_at')
            ->get();
    }

    public function render(): mixed
    {
        return view('livewire.portal.dashboard', [
            'reports' => $this->reports(),
            'sites' => $this->client->sites()->where('is_active', true)->get(),
        ]);
    }
}
