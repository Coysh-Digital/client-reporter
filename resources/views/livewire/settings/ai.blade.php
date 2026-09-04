<div>
    <x-page-header title="AI summaries" subtitle="Optionally add AI-written summaries to your reports." eyebrow="Workspace">
        <x-slot:actions>
            <button wire:click="test" class="cr-btn cr-btn-secondary">
                <span wire:loading.remove wire:target="test">Test connection</span>
                <span wire:loading wire:target="test">Testing…</span>
            </button>
            <button wire:click="save" class="cr-btn cr-btn-primary">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-lg bg-ok-soft px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="mx-auto max-w-3xl space-y-6">
        {{-- Settings tabs --}}
        <div class="flex gap-1 border-b border-line">
            <a href="{{ route('settings.edit') }}" wire:navigate class="border-b-2 border-transparent px-3 py-2 text-sm text-muted hover:text-ink">General</a>
            <a href="{{ route('settings.ai') }}" wire:navigate class="border-b-2 px-3 py-2 text-sm font-semibold text-ink" style="border-color:var(--color-accent)">AI summaries</a>
        </div>

        @if ($testResult)
            <div @class([
                'rounded-lg px-4 py-3 text-sm',
                'bg-ok-soft text-ok' => $testOk,
                'bg-danger-soft text-danger' => ! $testOk,
            ])>{{ $testResult }}</div>
        @endif

        {{-- Provider --}}
        <section class="cr-panel">
            <div class="border-b border-line px-5 py-3.5"><h2 class="cr-eyebrow">Provider</h2></div>
            <div class="space-y-4 px-5 py-5">
                <label class="flex cursor-pointer items-center gap-3">
                    <input type="checkbox" wire:model="enabled" class="peer sr-only">
                    <span class="relative h-5 w-9 shrink-0 rounded-full bg-line-strong transition peer-checked:bg-accent">
                        <span class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-4"></span>
                    </span>
                    <span class="text-sm text-ink">Enable AI summaries in reports</span>
                </label>
                <p class="text-xs text-muted">
                    When enabled, sections with “AI summary” switched on — and the “Month in review” block — get an AI-written
                    paragraph, produced when a report is generated. Nothing is sent to the provider until you turn this on.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="cr-label">Provider</label>
                        <select wire:model.live="provider" class="cr-input">
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic (Claude)</option>
                            <option value="ollama">Ollama (self-hosted)</option>
                        </select>
                        @error('provider') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="cr-label">Model</label>
                        <input wire:model="model" placeholder="Provider default" class="cr-input">
                        @error('model') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-faint">Leave blank to use the default for the chosen provider.</p>
                    </div>
                </div>

                @if ($provider !== 'ollama')
                    <div>
                        <label class="cr-label">API key</label>
                        <input type="password" wire:model="api_key" autocomplete="off"
                               placeholder="{{ $has_key ? 'A key is saved — leave blank to keep it' : 'Paste your API key' }}" class="cr-input">
                        @error('api_key') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-faint">
                            Stored encrypted and never shown again.
                            @if ($has_key) <span class="text-ok">A key is currently saved.</span> @endif
                        </p>
                    </div>
                @endif

                <div>
                    <label class="cr-label">API base URL</label>
                    <input wire:model="base_url" placeholder="Provider default" class="cr-input">
                    @error('base_url') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-faint">
                        {{ $provider === 'ollama' ? 'Where your Ollama server is reachable, e.g. http://127.0.0.1:11434.' : 'Only change this for a proxy or a self-hosted, OpenAI-compatible endpoint.' }}
                    </p>
                </div>
            </div>
        </section>

        {{-- Tone --}}
        <section class="cr-panel">
            <div class="border-b border-line px-5 py-3.5"><h2 class="cr-eyebrow">Tone &amp; style</h2></div>
            <div class="px-5 py-5">
                <label class="cr-label">House tone and style (optional)</label>
                <textarea wire:model="tone" rows="3" class="cr-input"
                          placeholder="e.g. Warm and plain-spoken, British English, avoid jargon, address the client as “you”."></textarea>
                @error('tone') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-faint">Applied to every AI summary, on top of each section’s own prompt.</p>

                <div class="mt-5">
                    <label class="cr-label">Summary label</label>
                    <input wire:model="summaryLabel" maxlength="60" placeholder="AI summary" class="cr-input max-w-xs">
                    @error('summaryLabel') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-faint">The label shown above AI-written summaries in reports. Rename it to match your brand (e.g. “Bolt Summary”).</p>
                </div>
            </div>
        </section>

        {{-- Per-component prompts --}}
        <section class="cr-panel">
            <div class="border-b border-line px-5 py-3.5"><h2 class="cr-eyebrow">Prompts</h2></div>
            <div class="space-y-5 px-5 py-5">
                <p class="text-xs text-muted">Edit the instruction sent to the AI for each component. Leave a box blank to use its default (shown as the placeholder).</p>
                @foreach ($promptBlocks as $type => $block)
                    <div wire:key="prompt-{{ $type }}">
                        <div class="flex items-center justify-between">
                            <label class="cr-label">{{ $block->label() }}</label>
                            @if (($prompts[$type] ?? '') !== '')
                                <button type="button" wire:click="resetPrompt('{{ $type }}')" class="text-xs text-muted hover:text-ink">Reset to default</button>
                            @endif
                        </div>
                        <textarea wire:model="prompts.{{ $type }}" rows="3" class="cr-input"
                                  placeholder="{{ $block->defaultAiPrompt() }}"></textarea>
                        @error('prompts.'.$type) <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
