<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Livewire\Integrations\Catalog;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_single_site_links_a_card_straight_to_connecting(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        $this->actingAs($manager)->get('/integrations')
            ->assertOk()
            ->assertSee('Connect on a site')
            ->assertSee(route('sites.integrations.connect', ['site' => $site, 'key' => 'wordpress']), escape: false);
    }

    public function test_a_viewer_gets_no_connect_links(): void
    {
        $viewer = User::factory()->viewer()->create();
        Site::factory()->create();

        $this->actingAs($viewer)->get('/integrations')
            ->assertOk()
            ->assertDontSee('Connect on a site');
    }

    public function test_managers_see_a_build_your_own_link_to_the_docs(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get('/integrations')
            ->assertOk()
            ->assertSee('Build your own')
            ->assertSee(config('client-reporter.docs.integrations'), escape: false);
    }

    public function test_viewers_do_not_see_build_your_own(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/integrations')
            ->assertOk()
            ->assertDontSee('Build your own');
    }

    public function test_several_sites_open_a_site_picker_instead_of_bouncing_to_the_list(): void
    {
        $manager = User::factory()->manager()->create();
        Site::factory()->create(['name' => 'First Site']);
        Site::factory()->create(['name' => 'Second Site']);

        $this->actingAs($manager)->get('/integrations')
            ->assertOk()
            ->assertSee('open-site-picker', false)
            ->assertSee('Connect on which site?')
            ->assertSee('First Site')
            ->assertSee('Second Site');
    }

    public function test_cards_show_connection_health_and_the_catalogue_can_be_filtered(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create(['integration_key' => 'uptimerobot', 'status' => ConnectionStatus::AuthExpired]);

        $this->actingAs($manager)->get('/integrations')
            ->assertOk()
            ->assertSee('1 of 1 site needs attention');

        Livewire::actingAs($manager)->test(Catalog::class)
            ->set('search', 'plausible')
            ->assertSee('Plausible')
            ->assertDontSee('UptimeRobot')
            ->set('search', '')
            ->call('setCategory', 'monitoring')
            ->assertSee('UptimeRobot')
            ->assertDontSee('Plausible')
            ->call('setCategory', 'bogus')
            ->assertSet('category', '');
    }
}
