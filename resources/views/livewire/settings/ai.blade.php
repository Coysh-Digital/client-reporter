<div>
    <x-page-header title="AI summaries" subtitle="Optionally add AI-written summaries to your reports." eyebrow="Workspace">
        <x-slot:actions>
            <x-button wire:click="test">
                <span wire:loading.remove wire:target="test">Test connection</span>
                <span wire:loading wire:target="test">Testing…</span>
            </x-button>
            <x-button variant="primary" wire:click="save">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving…</span>
            </x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="mx-auto max-w-3xl space-y-6">
        <x-tabs label="Settings sections" :items="[
            ['label' => 'General', 'href' => route('settings.edit')],
            ['label' => 'AI summaries', 'href' => route('settings.ai'), 'active' => true],
        ]" />

        @if ($testResult)
            <x-alert :variant="$testOk ? 'ok' : 'danger'">{{ $testResult }}</x-alert>
        @endif

        {{-- Provider --}}
        <section class="cr-panel">
            <div class="cr-panel-header"><h2 class="cr-eyebrow">Provider</h2></div>
            <div class="space-y-4 px-5 py-5">
                <x-toggle wire:model="enabled" label="Enable AI summaries in reports" />
                <p class="text-xs text-muted">
                    When enabled, sections with “AI summary” switched on — and the “Month in review” block — get an AI-written
                    paragraph, produced when a report is generated. Nothing is sent to the provider until you turn this on.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Provider" for="provider">
                        <select wire:model.live="provider" id="provider" class="cr-input">
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic (Claude)</option>
                            <option value="ollama">Ollama (self-hosted)</option>
                        </select>
                    </x-field>
                    <x-field label="Model" for="model" help="Leave blank to use the default for the chosen provider.">
                        <input wire:model="model" id="model" placeholder="Provider default" class="cr-input">
                    </x-field>
                </div>

                @if ($provider !== 'ollama')
                    <x-field label="API key" for="api_key" :help="$has_key ? 'Stored encrypted and never shown again. A key is currently saved.' : 'Stored encrypted and never shown again.'">
                        <input type="password" wire:model="api_key" id="api_key" autocomplete="off"
                               placeholder="{{ $has_key ? 'A key is saved — leave blank to keep it' : 'Paste your API key' }}" class="cr-input">
                    </x-field>
                @endif

                <x-field label="API base URL" for="base_url" :help="$provider === 'ollama' ? 'Where your Ollama server is reachable, e.g. http://127.0.0.1:11434.' : 'Only change this for a proxy or a self-hosted, OpenAI-compatible endpoint.'">
                    <input wire:model="base_url" id="base_url" placeholder="Provider default" class="cr-input">
                </x-field>
            </div>
        </section>

        {{-- Tone --}}
        <section class="cr-panel">
            <div class="cr-panel-header"><h2 class="cr-eyebrow">Tone &amp; style</h2></div>
            <div class="px-5 py-5">
                <x-field label="House tone and style" for="tone" optional help="Applied to every AI summary, on top of each section’s own prompt.">
                    <textarea wire:model="tone" id="tone" rows="3" class="cr-input"
                              placeholder="e.g. Warm and plain-spoken, British English, avoid jargon, address the client as “you”."></textarea>
                </x-field>

                <x-field label="Summary label" for="summaryLabel" class="mt-5" help="The label shown above AI-written summaries in reports. Rename it to match your brand (e.g. “Bolt Summary”).">
                    <input wire:model="summaryLabel" id="summaryLabel" maxlength="60" placeholder="AI summary" class="cr-input max-w-xs">
                </x-field>
            </div>
        </section>

        {{-- Per-component prompts --}}
        <section class="cr-panel">
            <div class="cr-panel-header"><h2 class="cr-eyebrow">Prompts</h2></div>
            <div class="space-y-5 px-5 py-5">
                <p class="text-xs text-muted">Edit the instruction sent to the AI for each component. Leave a box blank to use its default (shown as the placeholder).</p>
                @foreach ($promptBlocks as $type => $block)
                    <div wire:key="prompt-{{ $type }}">
                        <div class="flex items-center justify-between">
                            <label for="prompt-{{ $type }}" class="cr-label">{{ $block->label() }}</label>
                            @if (($prompts[$type] ?? '') !== '')
                                <button type="button" wire:click="resetPrompt('{{ $type }}')" class="cr-link text-xs">Reset to default</button>
                            @endif
                        </div>
                        <textarea wire:model="prompts.{{ $type }}" id="prompt-{{ $type }}" rows="3" class="cr-input"
                                  placeholder="{{ $block->defaultAiPrompt() }}"></textarea>
                        @error('prompts.'.$type) <p class="cr-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
