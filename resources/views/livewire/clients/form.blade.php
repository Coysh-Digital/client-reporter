<div>
    <x-page-header :title="$client ? 'Edit client' : 'New client'"
                   :subtitle="$client?->name ?? 'Add a business you report for.'" />

    <form wire:submit="save" class="cr-card max-w-xl px-6 py-6 space-y-5">
        <div>
            <label for="name" class="cr-label">Client name</label>
            <input wire:model="name" id="name" type="text" class="cr-input" required>
            @error('name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="contact_name" class="cr-label">Contact name</label>
                <input wire:model="contact_name" id="contact_name" type="text" class="cr-input">
                @error('contact_name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="contact_email" class="cr-label">Contact email</label>
                <input wire:model="contact_email" id="contact_email" type="email" class="cr-input">
                @error('contact_email') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="company" class="cr-label">Company <span class="text-faint">(optional)</span></label>
            <input wire:model="company" id="company" type="text" class="cr-input">
            @error('company') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="notes" class="cr-label">Internal notes <span class="text-faint">(optional)</span></label>
            <textarea wire:model="notes" id="notes" rows="3" class="cr-input"></textarea>
            @error('notes') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-muted">
            <input wire:model="is_active" type="checkbox" class="rounded border-line-strong text-accent focus:ring-accent">
            Client is active
        </label>

        <div class="flex items-center gap-3 border-t border-line pt-5">
            <button type="submit" class="cr-btn cr-btn-primary">{{ $client ? 'Save changes' : 'Create client' }}</button>
            <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}" wire:navigate class="cr-btn cr-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
