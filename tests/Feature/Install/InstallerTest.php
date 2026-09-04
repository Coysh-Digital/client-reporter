<?php

declare(strict_types=1);

namespace Tests\Feature\Install;

use App\Enums\UserRole;
use App\Livewire\Install\Wizard;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    private function markNotInstalled(): void
    {
        app(Settings::class)->forget('installed');
    }

    public function test_an_uninstalled_app_redirects_to_the_installer(): void
    {
        $this->markNotInstalled();

        $this->get('/login')->assertRedirect(route('install'));
        $this->get('/install')->assertOk()->assertSee('Server requirements');
    }

    public function test_the_installer_is_unreachable_once_installed(): void
    {
        // TestCase already marks installed.
        $this->get('/install')->assertRedirect(route('login'));
    }

    public function test_the_wizard_installs_the_application(): void
    {
        $this->markNotInstalled();

        // Point the env file at a temp location so the real .env is untouched.
        $dir = sys_get_temp_dir();
        $file = 'cr-install-test-'.uniqid().'.env';
        file_put_contents($dir.'/'.$file, "APP_URL=http://localhost\nDB_CONNECTION=sqlite\n");
        $this->app->useEnvironmentPath($dir);
        $this->app->loadEnvironmentFrom($file);

        Livewire::test(Wizard::class)
            ->set('step', 3)
            ->set('admin_name', 'Agency Owner')
            ->set('admin_email', 'owner@agency.test')
            ->set('admin_password', 'supersecret')
            ->set('admin_password_confirmation', 'supersecret')
            ->set('step', 4)
            ->set('agency_name', 'Bright Digital')
            ->set('app_url', 'https://reports.bright.test')
            ->set('primary_color', '#224466')
            ->call('install')
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', ['email' => 'owner@agency.test', 'role' => UserRole::Administrator->value]);
        $this->assertDatabaseHas('branding_profiles', ['agency_name' => 'Bright Digital', 'primary_color' => '#224466']);
        $this->assertTrue(app(Settings::class)->isInstalled());

        @unlink($dir.'/'.$file);
    }

    public function test_requirements_are_reported(): void
    {
        $this->markNotInstalled();

        $this->assertTrue((new Wizard)->requirementsMet());
    }
}
