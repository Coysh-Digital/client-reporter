<?php

declare(strict_types=1);

namespace Tests\Feature\Sites;

use App\Livewire\Sites\Import;
use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ImportFlowTest extends TestCase
{
    use RefreshDatabase;

    private function fakeWpMgr(): void
    {
        Http::fake(['manage.wpmgr.app/*' => Http::response([
            'items' => [
                ['id' => 's1', 'url' => 'https://alpha.test', 'name' => 'Alpha', 'client_name' => 'Alpha Ltd'],
                ['id' => 's2', 'url' => 'https://beta.test', 'name' => 'Beta'],
            ],
        ])]);
    }

    public function test_a_viewer_cannot_reach_the_import_screen(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/sites/import')->assertForbidden();
    }

    public function test_fetch_then_import_creates_clients_and_sites(): void
    {
        $this->fakeWpMgr();
        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(Import::class)
            ->set('provider', 'wpmgr')
            ->set('config.api_key', 'wpmgr_test')
            ->call('fetch')
            ->assertSet('fetched', true)
            ->assertSet('rows.0.name', 'Alpha')
            ->assertSet('rows.0.new_client_name', 'Alpha Ltd')
            ->call('import')
            ->assertSet('result.created', 2);

        $this->assertDatabaseHas('clients', ['name' => 'Alpha Ltd']);
        $this->assertDatabaseHas('sites', ['url' => 'https://alpha.test', 'cms_type' => 'wordpress']);
        $this->assertDatabaseHas('sites', ['url' => 'https://beta.test']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'sites.imported']);
    }

    public function test_it_maps_to_an_existing_client_and_skips_duplicates(): void
    {
        $this->fakeWpMgr();
        $manager = User::factory()->manager()->create();
        $client = Client::factory()->create(['name' => 'Existing Co']);
        Site::factory()->for($client)->create(['url' => 'https://alpha.test']);

        Livewire::actingAs($manager)->test(Import::class)
            ->set('provider', 'wpmgr')
            ->set('config.api_key', 'wpmgr_test')
            ->call('fetch')
            // Alpha is already imported and excluded by default.
            ->assertSet('rows.0.already', true)
            ->assertSet('rows.0.include', false)
            // Map Beta to the existing client.
            ->set('rows.1.client_choice', (string) $client->id)
            ->call('import')
            ->assertSet('result.created', 1);

        $this->assertSame(1, Client::count());
        $this->assertDatabaseHas('sites', ['url' => 'https://beta.test', 'client_id' => $client->id]);
        $this->assertSame(2, Site::count());
    }

    public function test_it_defaults_to_a_cms_that_has_sources(): void
    {
        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(Import::class)
            ->assertSet('cms', 'wordpress')
            ->assertSet('provider', 'wpmgr');
    }

    public function test_a_cms_without_sources_reports_none(): void
    {
        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(Import::class)
            ->set('cms', 'craft')
            ->assertSet('provider', '')
            ->assertSee('There are no import sources for Craft CMS yet');
    }

    public function test_a_rejected_key_surfaces_an_error(): void
    {
        Http::fake(['manage.wpmgr.app/*' => Http::response([], 401)]);
        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(Import::class)
            ->set('provider', 'wpmgr')
            ->set('config.api_key', 'bad')
            ->call('fetch')
            ->assertSet('fetched', false)
            ->assertSet('error', 'WPMgr rejected the API key. Check it has access to your sites.');
    }
}
