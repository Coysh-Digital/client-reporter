<div x-data="{ open: false }" class="relative inline-block">
    <button @click="open = !open" type="button" class="cr-btn cr-btn-primary">Share &amp; send</button>

    <div x-show="open" @click.outside="open = false" x-cloak
         class="absolute right-0 z-20 mt-1 w-96 rounded-lg border border-line bg-surface p-4 shadow-xl">
        @error('generate') <div class="mb-3 rounded bg-warn-soft px-3 py-2 text-xs text-warn">{{ $message }}</div> @enderror
        @if (session('share_status')) <div class="mb-3 rounded bg-ok-soft px-3 py-2 text-xs text-ok">{{ session('share_status') }}</div> @endif

        {{-- Share links --}}
        <h3 class="text-xs font-medium uppercase tracking-wide text-faint">Secure link</h3>
        @if ($newLink)
            <div class="mt-2 rounded-md border border-ok/30 bg-ok-soft px-3 py-2">
                <p class="text-xs text-ok">New link created — copy it now:</p>
                <input readonly value="{{ $newLink }}" class="cr-input mt-1 text-xs" onclick="this.select()">
            </div>
        @endif
        <div class="mt-2 grid grid-cols-2 gap-2">
            <div>
                <label class="cr-label text-xs">Expires after (days)</label>
                <input type="number" wire:model="expiryDays" min="1" placeholder="Never" class="cr-input text-sm">
            </div>
            <div>
                <label class="cr-label text-xs">Password (optional)</label>
                <input type="text" wire:model="password" placeholder="None" class="cr-input text-sm">
            </div>
        </div>
        @error('expiryDays') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        <button wire:click="createLink" class="cr-btn cr-btn-secondary mt-2 w-full text-sm">Create link</button>

        @if ($shares->isNotEmpty())
            <ul class="mt-3 space-y-1.5">
                @foreach ($shares as $share)
                    <li wire:key="share-{{ $share->id }}" class="flex items-center justify-between text-xs text-muted">
                        <span>
                            {{ $share->requiresPassword() ? '🔒 ' : '' }}Link · {{ $share->views }} views
                            @if ($share->expires_at) · expires {{ $share->expires_at->isoFormat('D MMM') }} @endif
                        </span>
                        <button wire:click="revoke({{ $share->id }})" class="text-danger hover:underline">Revoke</button>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Email --}}
        <div class="mt-4 border-t border-line pt-3">
            <h3 class="text-xs font-medium uppercase tracking-wide text-faint">Email to client</h3>
            <input type="email" wire:model="emailTo" placeholder="client@example.com" class="cr-input mt-2 text-sm">
            @error('emailTo') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            <textarea wire:model="emailMessage" rows="2" placeholder="Optional message" class="cr-input mt-2 text-sm"></textarea>
            <label class="mt-2 flex items-center gap-2 text-xs text-muted">
                <input type="checkbox" wire:model="attachPdf" class="rounded border-line-strong text-accent focus:ring-accent">
                Attach a PDF copy
            </label>
            <button wire:click="sendEmail" wire:loading.attr="disabled" class="cr-btn cr-btn-primary mt-2 w-full text-sm">
                <span wire:loading.remove wire:target="sendEmail">Send email</span>
                <span wire:loading wire:target="sendEmail">Sending…</span>
            </button>
        </div>

        <div class="mt-4 border-t border-line pt-3">
            <a href="{{ route('reports.pdf', $report) }}" class="text-sm text-accent hover:underline">Download PDF ↓</a>
        </div>
    </div>
</div>
