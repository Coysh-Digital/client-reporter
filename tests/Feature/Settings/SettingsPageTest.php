<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Manage;
use App\Models\User;
use App\Support\Settings;
use App\Support\UpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_open_settings(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get('/settings')->assertOk()->assertSee('Software updates');
    }

    public function test_a_manager_cannot_open_settings(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get('/settings')->assertForbidden();
    }

    public function test_saving_persists_settings(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Manage::class)
            ->set('updates_enabled', false)
            ->set('pdf_driver', 'browsershot')
            ->set('default_share_expiry_days', 30)
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(Settings::class);
        $settings->flush();
        $this->assertFalse((bool) $settings->get('updates_enabled'));
        $this->assertSame('browsershot', $settings->get('pdf_driver'));
        $this->assertSame(30, (int) $settings->get('default_share_expiry_days'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.updated']);
    }

    public function test_an_invalid_pdf_driver_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Manage::class)
            ->set('pdf_driver', 'wkhtmltopdf')
            ->call('save')
            ->assertHasErrors('pdf_driver');
    }

    public function test_disabling_update_checks_hides_an_available_update(): void
    {
        $admin = User::factory()->administrator()->create();
        $settings = app(Settings::class);
        $settings->set('update_check', ['latest' => '99.0.0', 'url' => 'https://example.test', 'checked_at' => now()->toIso8601String()]);

        // Enabled: the update shows.
        $settings->set('updates_enabled', true);
        $this->assertTrue(app(UpdateChecker::class)->status()['update_available']);

        // Disabled: it is suppressed.
        $settings->set('updates_enabled', false);
        $this->assertFalse(app(UpdateChecker::class)->status()['update_available']);
    }
}
