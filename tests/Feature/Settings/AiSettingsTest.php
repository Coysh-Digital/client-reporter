<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Ai;
use App\Models\AiSetting;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_cannot_open_ai_settings(): void
    {
        $this->actingAs(User::factory()->manager()->create())->get('/settings/ai')->assertForbidden();
    }

    public function test_saving_encrypts_the_key_and_never_returns_it(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Ai::class)
            ->set('enabled', true)
            ->set('provider', 'openai')
            ->set('model', 'gpt-4o-mini')
            ->set('api_key', 'sk-super-secret')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('api_key', '')          // cleared from the component
            ->assertSet('has_key', true);

        $setting = AiSetting::current();
        $this->assertSame('sk-super-secret', $setting->apiKey());

        // The stored column is ciphertext, not the plaintext key.
        $raw = DB::table('ai_settings')->value('credentials');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('sk-super-secret', $raw);

        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.ai.updated']);
    }

    public function test_a_blank_key_keeps_the_existing_secret(): void
    {
        AiSetting::create([
            'enabled' => true,
            'provider' => 'openai',
            'credentials' => ['api_key' => 'sk-existing'],
        ]);

        Livewire::actingAs(User::factory()->administrator()->create())->test(Ai::class)
            ->set('model', 'gpt-4o')
            ->set('api_key', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('sk-existing', AiSetting::current()->apiKey());
        $this->assertSame('gpt-4o', AiSetting::current()->model);
    }

    public function test_resetting_a_prompt_forgets_the_override(): void
    {
        $settings = app(Settings::class);
        $settings->set('ai.prompt.analytics.site_traffic', 'CUSTOM.');

        Livewire::actingAs(User::factory()->administrator()->create())->test(Ai::class)
            ->call('resetPrompt', 'analytics.site_traffic')
            ->assertSet('prompts.analytics.site_traffic', '');

        $settings->flush();
        $this->assertFalse($settings->has('ai.prompt.analytics.site_traffic'));
    }

    public function test_the_test_button_reports_the_verification_result(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);

        Livewire::actingAs(User::factory()->administrator()->create())->test(Ai::class)
            ->set('provider', 'openai')
            ->set('api_key', 'sk-test')
            ->call('test')
            ->assertSet('testOk', true);
    }
}
