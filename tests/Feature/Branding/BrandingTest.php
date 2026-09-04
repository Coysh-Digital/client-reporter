<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Livewire\Branding\Manage;
use App\Models\BrandingProfile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_edit_global_branding(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Manage::class)
            ->set('agency_name', 'Acme Digital')
            ->set('primary_color', '#123456')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('branding_profiles', [
            'brandable_type' => null,
            'agency_name' => 'Acme Digital',
            'primary_color' => '#123456',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'branding.updated']);
    }

    public function test_an_admin_can_choose_a_google_font_for_reports(): void
    {
        $admin = User::factory()->administrator()->create();

        // The font picker sets the property to a full CSS stack.
        Livewire::actingAs($admin)->test(Manage::class)
            ->set('heading_font', "'Merriweather', Georgia, 'Times New Roman', serif")
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('branding_profiles', [
            'brandable_type' => null,
            'heading_font' => "'Merriweather', Georgia, 'Times New Roman', serif",
        ]);
    }

    public function test_invalid_hex_colour_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Manage::class)
            ->set('primary_color', 'notacolour')
            ->call('save')
            ->assertHasErrors('primary_color');
    }

    public function test_a_manager_cannot_edit_global_branding(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get('/branding')->assertForbidden();
    }

    public function test_a_manager_can_edit_client_branding(): void
    {
        $manager = User::factory()->manager()->create();
        $client = Client::factory()->create();

        Livewire::actingAs($manager)->test(Manage::class, ['client' => $client])
            ->assertSet('scope', 'client')
            ->set('primary_color', '#abcdef')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('branding_profiles', [
            'brandable_type' => $client->getMorphClass(),
            'brandable_id' => $client->id,
            'primary_color' => '#abcdef',
        ]);
    }

    public function test_a_logo_can_be_uploaded(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Manage::class)
            ->set('logo', UploadedFile::fake()->image('logo.png', 200, 60))
            ->call('save')
            ->assertHasNoErrors();

        $profile = BrandingProfile::query()->whereNull('brandable_type')->first();
        $this->assertNotNull($profile->logo_path);
        Storage::disk('public')->assertExists($profile->logo_path);
    }
}
