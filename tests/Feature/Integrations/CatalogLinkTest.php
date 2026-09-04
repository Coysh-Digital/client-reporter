<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_managers_see_an_add_integration_link_to_the_docs(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get('/integrations')
            ->assertOk()
            ->assertSee('Add integration')
            ->assertSee(config('client-reporter.docs.integrations'), escape: false);
    }

    public function test_viewers_do_not_see_add_integration(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/integrations')
            ->assertOk()
            ->assertDontSee('Add integration');
    }
}
