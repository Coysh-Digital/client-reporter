<div class="inline-block">
    <x-button variant="primary" icon="envelope" x-on:click="$dispatch('open-share-report')">Share &amp; send</x-button>

    <x-dialog name="share-report" title="Share &amp; send" description="A secure link, an email to the client, or a PDF to keep.">
        <div x-data="{ tab: 'link' }">
            <div role="tablist" aria-label="Sharing options" class="cr-segmented mb-4">
                <button type="button" role="tab" id="share-tab-link" class="cr-segmented-item" x-bind:aria-selected="tab === 'link'" x-bind:aria-pressed="tab === 'link'" x-on:click="tab = 'link'">Link</button>
                <button type="button" role="tab" id="share-tab-email" class="cr-segmented-item" x-bind:aria-selected="tab === 'email'" x-bind:aria-pressed="tab === 'email'" x-on:click="tab = 'email'">Email</button>
                <button type="button" role="tab" id="share-tab-pdf" class="cr-segmented-item" x-bind:aria-selected="tab === 'pdf'" x-bind:aria-pressed="tab === 'pdf'" x-on:click="tab = 'pdf'">PDF</button>
            </div>

            @error('generate') <x-alert variant="warn" class="mb-3">{{ $message }}</x-alert> @enderror

            {{-- Secure link --}}
            <section role="tabpanel" aria-labelledby="share-tab-link" x-show="tab === 'link'" class="space-y-4">
                @if ($newLink)
                    <div class="rounded-lg border border-ok/30 bg-ok-soft px-3 py-3" x-data="{ copied: false }">
                        <p class="text-xs font-semibold text-ok">New link created — copy it now, it is not shown again.</p>
                        <div class="mt-2 flex items-center gap-2">
                            <input readonly value="{{ $newLink }}" x-ref="link" x-on:click="$el.select()" class="cr-input text-xs" aria-label="Share link">
                            <x-button size="sm" icon="clipboard-document" x-on:click="navigator.clipboard?.writeText($refs.link.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                                <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
                            </x-button>
                        </div>
                    </div>
                @endif

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Expires after (days)" for="share-expiry" name="expiryDays" help="Leave blank for a link that never expires.">
                        <input type="number" id="share-expiry" wire:model="expiryDays" min="1" max="3650" placeholder="Never" class="cr-input">
                    </x-field>
                    <x-field label="Password" for="share-password" name="password" optional help="At least 10 characters. Tell the client separately.">
                        <div class="relative" x-data="{ reveal: false }">
                            <input x-bind:type="reveal ? 'text' : 'password'" id="share-password" wire:model="password" autocomplete="new-password" placeholder="None" class="cr-input pr-16">
                            <button type="button" class="absolute inset-y-0 right-2 text-xs font-medium text-muted hover:text-ink" x-on:click="reveal = !reveal" x-text="reveal ? 'Hide' : 'Show'" aria-controls="share-password"></button>
                        </div>
                    </x-field>
                </div>
                <x-button wire:click="createLink" icon="plus">
                    <span wire:loading.remove wire:target="createLink">Create link</span>
                    <span wire:loading wire:target="createLink">Creating…</span>
                </x-button>

                @if ($shares->isNotEmpty())
                    <div class="border-t border-line pt-3">
                        <p class="cr-eyebrow mb-2">Active links</p>
                        <ul class="divide-y divide-line">
                            @foreach ($shares as $share)
                                <li wire:key="share-{{ $share->id }}" class="flex items-center justify-between gap-3 py-2 text-sm">
                                    <span class="min-w-0 text-muted">
                                        <span class="inline-flex items-center gap-1.5">
                                            @if ($share->requiresPassword())<x-icon name="lock-closed" class="h-3 w-3 text-faint" /><span class="sr-only">Password protected</span>@endif
                                            @if ($share->views > 0)
                                                <span class="font-semibold text-ok">Opened</span>
                                            @else
                                                <span class="text-faint">Not opened yet</span>
                                            @endif
                                        </span>
                                        <span class="block text-xs text-faint">
                                            @if ($share->views > 0)
                                                {{ $share->views }} {{ Str::plural('open', $share->views) }}{{ $share->last_viewed_at ? ' · last '.$share->last_viewed_at->diffForHumans() : '' }} ·
                                            @endif
                                            Sent {{ $share->created_at?->isoFormat('D MMM') }}{{ $share->expires_at ? ' · expires '.$share->expires_at->isoFormat('D MMM YYYY') : '' }}
                                        </span>
                                    </span>
                                    <x-confirm-button action="revoke({{ $share->id }})" title="Revoke this link?" message="Anyone who has it will see “this link is no longer available”." confirm="Revoke" :danger="true" class="cr-btn cr-btn-ghost cr-btn-sm text-danger">Revoke</x-confirm-button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            {{-- Email --}}
            <section role="tabpanel" aria-labelledby="share-tab-email" x-show="tab === 'email'" x-cloak class="space-y-4">
                <x-field label="Send to" for="share-email" name="emailTo" required>
                    <input type="email" id="share-email" wire:model="emailTo" placeholder="client@example.com" class="cr-input">
                </x-field>
                <x-field label="Message" for="share-message" name="emailMessage" optional help="Shown above the report link in the email.">
                    <textarea id="share-message" wire:model="emailMessage" rows="3" class="cr-input"></textarea>
                </x-field>
                <x-checkbox wire:model="attachPdf" id="share-attach" label="Attach a PDF copy" />
                <x-button variant="primary" wire:click="sendEmail" wire:loading.attr="disabled" icon="envelope">
                    <span wire:loading.remove wire:target="sendEmail">Send email</span>
                    <span wire:loading wire:target="sendEmail">Sending…</span>
                </x-button>
                <p class="text-xs text-faint">The email carries a fresh secure link with your default expiry, in the agency's branding.</p>
            </section>

            {{-- PDF --}}
            <section role="tabpanel" aria-labelledby="share-tab-pdf" x-show="tab === 'pdf'" x-cloak class="space-y-4">
                <p class="text-sm text-muted">Download the frozen report as a PDF to attach to your own email or keep on file.</p>
                <x-button variant="primary" :href="route('reports.pdf', $report)" :navigate="false" icon="file-chart-column">Download PDF</x-button>
            </section>
        </div>
    </x-dialog>
</div>
