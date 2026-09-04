<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Ai\AiSummaryClientFactory;
use App\Models\AiSetting;
use App\Reporting\BlockTypeRegistry;
use App\Reporting\Contracts\BlockType;
use App\Support\AuditLogger;
use App\Support\Settings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Configures the optional AI report-summary provider and the editable prompts.
 * The API key is write-only from the browser's perspective — it is never sent
 * back to the client, and a blank field on save keeps the stored key.
 */
#[Layout('components.layouts.app')]
#[Title('AI summaries')]
class Ai extends Component
{
    public bool $enabled = false;

    public string $provider = 'openai';

    public string $model = '';

    public string $base_url = '';

    /** New key entered by the user; never populated from storage. */
    public string $api_key = '';

    /** Whether a key is already stored (shown as an indicator only). */
    public bool $has_key = false;

    public string $tone = '';

    /** Client-facing label for AI summaries in reports (e.g. "Bolt Summary"). */
    public string $summaryLabel = '';

    /** @var array<string, string> Per-component prompt overrides, keyed by block type. */
    public array $prompts = [];

    public string $testResult = '';

    public bool $testOk = false;

    public function mount(Settings $settings): void
    {
        $this->authorize('manage-settings');

        $setting = AiSetting::current();
        $this->enabled = (bool) $setting->enabled;
        $this->provider = $setting->provider ?? 'openai';
        $this->model = (string) $setting->model;
        $this->base_url = (string) $setting->base_url;
        $this->has_key = $setting->apiKey() !== null;

        $this->tone = (string) $settings->get('ai.tone', '');
        $this->summaryLabel = (string) $settings->get('ai.summary_label', '');

        foreach ($this->promptBlocks() as $type) {
            $this->prompts[$type->type()] = (string) $settings->get('ai.prompt.'.$type->type(), '');
        }
    }

    public function save(Settings $settings, AuditLogger $audit): void
    {
        $this->authorize('manage-settings');

        $this->validate([
            'enabled' => ['boolean'],
            'provider' => ['required', 'in:openai,anthropic,ollama'],
            'model' => ['nullable', 'string', 'max:120'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'tone' => ['nullable', 'string', 'max:2000'],
            'summaryLabel' => ['nullable', 'string', 'max:60'],
            'prompts.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $setting = AiSetting::current();
        $setting->fill([
            'enabled' => $this->enabled,
            'provider' => $this->provider,
            'model' => $this->model !== '' ? $this->model : null,
            'base_url' => $this->base_url !== '' ? $this->base_url : null,
        ]);

        // Only overwrite the key when a new one was entered; blank keeps it.
        if ($this->api_key !== '') {
            $setting->credentials = ['api_key' => $this->api_key];
        }

        $setting->save();

        $settings->set('ai.tone', $this->tone);
        trim($this->summaryLabel) !== '' ? $settings->set('ai.summary_label', $this->summaryLabel) : $settings->forget('ai.summary_label');
        foreach ($this->prompts as $type => $prompt) {
            $key = 'ai.prompt.'.$type;
            trim($prompt) !== '' ? $settings->set($key, $prompt) : $settings->forget($key);
        }

        $this->api_key = '';
        $this->has_key = $setting->apiKey() !== null;

        $audit->log('settings.ai.updated');

        session()->flash('status', 'AI settings saved.');
    }

    /**
     * Clear a per-component prompt override, reverting to the block's default.
     */
    public function resetPrompt(string $type, Settings $settings): void
    {
        $this->authorize('manage-settings');

        $settings->forget('ai.prompt.'.$type);
        $this->prompts[$type] = '';
    }

    public function test(AiSummaryClientFactory $factory): void
    {
        $this->authorize('manage-settings');

        // Build a transient setting from the current form so the test reflects
        // unsaved edits, falling back to the stored key when the field is blank.
        $setting = AiSetting::current();
        $setting->fill([
            'provider' => $this->provider,
            'model' => $this->model !== '' ? $this->model : null,
            'base_url' => $this->base_url !== '' ? $this->base_url : null,
        ]);
        if ($this->api_key !== '') {
            $setting->credentials = ['api_key' => $this->api_key];
        }

        $result = $factory->make($setting)->verify();

        $this->testOk = $result->ok;
        $this->testResult = $result->message;
    }

    public function render(): mixed
    {
        return view('livewire.settings.ai', [
            'promptBlocks' => $this->promptBlocks(),
        ]);
    }

    /**
     * The blocks that expose an editable AI prompt (the AI-capable sections and
     * the report-level roundup), keyed by type in registry order.
     *
     * @return array<string, BlockType>
     */
    private function promptBlocks(): array
    {
        $blocks = [];
        foreach (app(BlockTypeRegistry::class)->all() as $type => $block) {
            if ($block->defaultAiPrompt() !== null) {
                $blocks[$type] = $block;
            }
        }

        return $blocks;
    }
}
