<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Users')]
class Index extends Component
{
    public function toggleActive(int $userId, AuditLogger $audit): void
    {
        $this->authorize('manage-users');

        $user = User::query()->findOrFail($userId);

        if ($user->is($this->currentUser())) {
            $this->addError('user', 'You cannot deactivate your own account.');

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);
        $audit->log($user->is_active ? 'user.activated' : 'user.deactivated', $user);
    }

    public function delete(int $userId, AuditLogger $audit): void
    {
        $this->authorize('manage-users');

        $user = User::query()->findOrFail($userId);

        if ($user->is($this->currentUser())) {
            $this->addError('user', 'You cannot delete your own account.');

            return;
        }

        $audit->log('user.deleted', $user, metadata: ['email' => $user->email]);
        $user->delete();
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /**
     * @return Collection<int, User>
     */
    public function users(): Collection
    {
        return User::query()->orderBy('name')->get();
    }

    public function render(): mixed
    {
        return view('livewire.users.index', ['users' => $this->users()]);
    }
}
