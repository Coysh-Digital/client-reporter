<div>
    <x-page-header title="Users" subtitle="Agency staff and client portal accounts." eyebrow="Workspace">
        <x-slot:actions>
            <a href="{{ route('users.create') }}" wire:navigate class="cr-btn cr-btn-primary">
                <x-icon name="plus" class="h-3.5 w-3.5" />
                New user
            </a>
        </x-slot:actions>
    </x-page-header>

    @error('user') <div class="mb-4 rounded-md bg-danger-soft px-3 py-2 text-sm text-danger">{{ $message }}</div> @enderror

    <div class="cr-panel">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-line text-left text-[10.5px] font-bold uppercase tracking-[0.07em] text-faint">
                    <th class="px-5 py-2.5 font-bold">Name</th>
                    <th class="px-5 py-2.5 font-bold">Role</th>
                    <th class="px-5 py-2.5 font-bold">Status</th>
                    <th class="px-5 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="hover:bg-paper">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <x-avatar :name="$user->name" shape="circle" />
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-ink">{{ $user->name }}</div>
                                    <div class="truncate text-xs text-faint">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <x-badge variant="accent">{{ $user->role->label() }}</x-badge>
                        </td>
                        <td class="px-5 py-3">
                            @if ($user->is_active)
                                <x-badge variant="ok">Active</x-badge>
                            @else
                                <x-badge variant="danger">Inactive</x-badge>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('users.edit', $user) }}" wire:navigate class="text-muted hover:text-ink">Edit</a>
                                <button wire:click="toggleActive({{ $user->id }})" class="text-muted hover:text-ink">
                                    {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                                <button wire:click="delete({{ $user->id }})"
                                        wire:confirm="Delete {{ $user->name }}? This cannot be undone."
                                        class="text-danger hover:underline">Delete</button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
