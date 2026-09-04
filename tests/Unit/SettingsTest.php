<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): Settings
    {
        return app(Settings::class);
    }

    public function test_it_stores_and_reads_scalar_and_array_values(): void
    {
        $settings = $this->settings();

        $settings->set('agency_name', 'Acme Digital');
        $settings->set('mail', ['from' => 'hello@acme.test']);

        $this->assertSame('Acme Digital', $settings->get('agency_name'));
        $this->assertSame(['from' => 'hello@acme.test'], $settings->get('mail'));
    }

    public function test_it_returns_default_for_missing_key(): void
    {
        $this->assertSame('fallback', $this->settings()->get('missing', 'fallback'));
        $this->assertFalse($this->settings()->has('missing'));
    }

    public function test_is_installed_reflects_the_installed_flag(): void
    {
        $this->settings()->forget('installed');
        $this->assertFalse($this->settings()->isInstalled());

        $this->settings()->set('installed', true);

        $this->assertTrue($this->settings()->isInstalled());
    }

    public function test_forget_removes_a_setting(): void
    {
        $settings = $this->settings();
        $settings->set('temp', 'value');
        $settings->forget('temp');

        $this->assertFalse($settings->has('temp'));
    }
}
