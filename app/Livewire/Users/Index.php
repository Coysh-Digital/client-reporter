<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Livewire\Concerns\SortsAndFilters;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Users')]
class Index extends Component
{
    use SortsAndFilters;
    use WithPagination;

    /** '' (all) or a UserRole value */
    #[Url]
    public string $role = '';

    public function updatingRole(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    protected function sortable(): array
    {
        return ['name' => 'name', 'email' => 'email', 'role' => 'role', 'status' => 'is_active', 'last_login' => 'last_login_at'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['name', 'email'];
    }

    public function toggleActive(int $userId, AuditLogger $audit): void
    {
        $this->authorize('manage-users');

        $user = User::query()->findOrFail($userId);

        if ($user->is($this->currentUser())) {
            $this->addError('user', 'You cannot deactivate your own account.');

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);

        // A deactivated account keeps no API access either.
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

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
        $user->tokens()->delete();
        $user->delete();
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function users(): LengthAwarePaginator
    {
        $query = User::query()
            ->with('client')
            ->when(UserRole::tryFrom($this->role) !== null, fn ($q) => $q->where('role', $this->role));

        return $this->applySortAndSearch($query)->paginate(25);
    }

    public function render(): mixed
    {
        return view('livewire.users.index', ['users' => $this->users()]);
    }
}
