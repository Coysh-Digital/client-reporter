<div>
    <x-breadcrumbs :items="[['label' => 'Clients', 'href' => route('clients.index')], ['label' => $client?->name ?? 'New client']]" />
    <x-page-header :title="$client ? 'Edit client' : 'New client'"
                   :subtitle="$client?->name ?? 'Add a business you report for.'" />

    <form wire:submit="save" class="cr-card max-w-xl px-6 py-6 space-y-5">
        <x-field label="Client name" for="name" required>
            <input wire:model="name" id="name" type="text" class="cr-input" required>
        </x-field>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="Contact name" for="contact_name">
                <input wire:model="contact_name" id="contact_name" type="text" class="cr-input">
            </x-field>
            <x-field label="Contact email" for="contact_email">
                <input wire:model="contact_email" id="contact_email" type="email" class="cr-input">
            </x-field>
        </div>

        <x-field label="Company" for="company" optional>
            <input wire:model="company" id="company" type="text" class="cr-input">
        </x-field>

        <x-field label="Internal notes" for="notes" optional help="Only your team sees these.">
            <textarea wire:model="notes" id="notes" rows="3" class="cr-input"></textarea>
        </x-field>

        <x-checkbox wire:model="is_active" id="is_active" label="Client is active" />

        <div class="flex items-center gap-3 border-t border-line pt-5">
            <x-button type="submit" variant="primary">{{ $client ? 'Save changes' : 'Create client' }}</x-button>
            <x-button :href="$client ? route('clients.show', $client) : route('clients.index')">Cancel</x-button>
        </div>
    </form>
</div>
