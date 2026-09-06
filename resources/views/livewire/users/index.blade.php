<div>
    <x-page-header title="Users" subtitle="Agency staff and client portal accounts." eyebrow="Workspace">
        <x-slot:actions>
            <x-button variant="primary" :href="route('users.create')" icon="plus">New user</x-button>
        </x-slot:actions>
    </x-page-header>

    @error('user') <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert> @enderror

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <label class="sr-only" for="users-search">Search users</label>
        <input wire:model.live.debounce.300ms="search" id="users-search" type="search" placeholder="Search by name or email…" class="cr-input max-w-xs">
        <label class="sr-only" for="users-role">Role</label>
        <select wire:model.live="role" id="users-role" class="cr-input w-auto py-1.5 pr-8 text-sm">
            <option value="">All roles</option>
            @foreach (\App\Enums\UserRole::cases() as $roleOption)
                <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
            @endforeach
        </select>
    </div>

    @if ($users->isEmpty())
        <x-empty-state icon="user-group" title="No users match" description="Try a different search or clear the role filter." />
    @else
        <x-table caption="Users">
            <thead>
                <tr>
                    <x-th sort="name" :current="$this->currentSort()" :direction="$this->currentDirection()">Name</x-th>
                    <x-th sort="role" :current="$this->currentSort()" :direction="$this->currentDirection()">Role</x-th>
                    <x-th sort="status" :current="$this->currentSort()" :direction="$this->currentDirection()">Status</x-th>
                    <x-th sort="last_login" :current="$this->currentSort()" :direction="$this->currentDirection()">Last sign-in</x-th>
                    <x-th><span class="sr-only">Actions</span></x-th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <x-td>
                            <div class="flex items-center gap-3">
                                <x-avatar :name="$user->name" shape="circle" aria-hidden="true" />
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-ink">{{ $user->name }}</div>
                                    <div class="truncate text-xs text-faint">{{ $user->email }}</div>
                                </div>
                            </div>
                        </x-td>
                        <x-td nowrap>
                            <x-badge :variant="$user->isClient() ? 'neutral' : 'accent'">{{ $user->role->label() }}</x-badge>
                            @if ($user->isClient() && $user->client)
                                <span class="ml-1 text-xs text-faint">{{ $user->client->name }}</span>
                            @endif
                        </x-td>
                        <x-td nowrap>
                            @if ($user->is_active)
                                <x-badge variant="ok">Active</x-badge>
                            @else
                                <x-badge variant="danger">Inactive</x-badge>
                            @endif
                        </x-td>
                        <x-td nowrap><span class="text-xs text-muted">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</span></x-td>
                        <x-td align="right" nowrap>
                            <x-dropdown>
                                <x-slot:trigger>
                                    <button type="button" class="cr-btn-icon" aria-label="Actions for {{ $user->name }}">
                                        <x-icon name="ellipsis-horizontal" class="h-4 w-4" />
                                    </button>
                                </x-slot:trigger>
                                <x-dropdown-item :href="route('users.edit', $user)" icon="pencil-square">Edit</x-dropdown-item>
                                <button type="button" role="menuitem" class="cr-menu-item" wire:click="toggleActive({{ $user->id }})" x-on:click="close()">
                                    <x-icon :name="$user->is_active ? 'x-circle' : 'check-circle'" class="h-3.5 w-3.5 shrink-0 text-faint" />
                                    {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                                <div class="cr-menu-separator"></div>
                                <x-confirm-button role="menuitem" class="cr-menu-item cr-menu-item-danger"
                                    action="delete({{ $user->id }})"
                                    title="Delete {{ $user->name }}?"
                                    message="They will lose access immediately. This cannot be undone."
                                    confirm="Delete user" :danger="true">
                                    <x-icon name="trash-can" class="h-3.5 w-3.5 shrink-0" /> Delete
                                </x-confirm-button>
                            </x-dropdown>
                        </x-td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-4">{{ $users->links('vendor.pagination.cr') }}</div>
    @endif
</div>
