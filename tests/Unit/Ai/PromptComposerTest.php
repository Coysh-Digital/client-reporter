<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\PromptComposer;
use App\Reporting\Blocks\Analytics\SiteTrafficBlock;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_tone_is_appended_to_the_system_prompt(): void
    {
        app(Settings::class)->set('ai.tone', 'Warm and plain-spoken, British English.');

        $messages = app(PromptComposer::class)->summaryFor(new SiteTrafficBlock, ['metrics' => ['Visitors' => 1]]);

        $this->assertStringContainsString('Warm and plain-spoken, British English.', $messages->system);
    }

    public function test_stored_override_takes_precedence_over_the_block_default(): void
    {
        $composer = app(PromptComposer::class);
        $block = new SiteTrafficBlock;

        $default = $composer->summaryFor($block, ['metrics' => []]);
        $this->assertStringContainsString((string) $block->defaultAiPrompt(), $default->user);

        app(Settings::class)->set('ai.prompt.'.$block->type(), 'CUSTOM INSTRUCTION.');
        $overridden = $composer->summaryFor($block, ['metrics' => []]);

        $this->assertStringContainsString('CUSTOM INSTRUCTION.', $overridden->user);
        $this->assertStringNotContainsString((string) $block->defaultAiPrompt(), $overridden->user);
    }

    public function test_forgetting_an_override_falls_back_to_the_default(): void
    {
        $settings = app(Settings::class);
        $composer = app(PromptComposer::class);
        $block = new SiteTrafficBlock;

        $settings->set('ai.prompt.'.$block->type(), 'CUSTOM.');
        $settings->forget('ai.prompt.'.$block->type());

        $this->assertStringContainsString((string) $block->defaultAiPrompt(), $composer->summaryFor($block, [])->user);
    }

    public function test_only_the_provided_facts_are_serialised_into_the_prompt(): void
    {
        $messages = app(PromptComposer::class)->summaryFor(new SiteTrafficBlock, ['metrics' => ['Visitors' => 1200]]);

        $this->assertStringContainsString('"Visitors": 1200', $messages->user);
        $this->assertStringContainsString('Data (JSON):', $messages->user);
    }
}
