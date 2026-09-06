<?php

declare(strict_types=1);

namespace Tests\Feature\Lists;

use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Sites\Index as SitesIndex;
use App\Livewire\Templates\Index as TemplatesIndex;
use App\Livewire\Users\Index as UsersIndex;
use App\Models\Client;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SortsAndFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_can_be_sorted_by_site_count_in_either_direction(): void
    {
        $manager = User::factory()->manager()->create();
        $few = Client::factory()->create(['name' => 'Few Sites']);
        $many = Client::factory()->create(['name' => 'Many Sites']);
        Site::factory()->count(3)->create(['client_id' => $many->id]);
        Site::factory()->create(['client_id' => $few->id]);

        $component = Livewire::actingAs($manager)->test(ClientsIndex::class)
            ->call('sortBy', 'sites')
            ->assertSet('sort', 'sites')
            ->assertSet('direction', 'asc');
        $component->assertSeeInOrder(['Few Sites', 'Many Sites']);

        $component->call('sortBy', 'sites')->assertSet('direction', 'desc');
        $component->assertSeeInOrder(['Many Sites', 'Few Sites']);
    }

    public function test_an_unknown_sort_key_is_ignored(): void
    {
        $manager = User::factory()->manager()->create();
        Client::factory()->create(['name' => 'Beta']);
        Client::factory()->create(['name' => 'Alpha']);

        Livewire::actingAs($manager)->test(ClientsIndex::class)
            ->call('sortBy', 'password')
            ->assertSet('sort', '')
            ->assertSeeInOrder(['Alpha', 'Beta']);
    }

    public function test_client_search_matches_company_and_contact(): void
    {
        $manager = User::factory()->manager()->create();
        Client::factory()->create(['name' => 'Acme', 'company' => 'Acme Holdings Ltd', 'contact_email' => 'jo@acme.test']);
        Client::factory()->create(['name' => 'Other', 'company' => null, 'contact_email' => 'sam@other.test']);

        Livewire::actingAs($manager)->test(ClientsIndex::class)
            ->set('search', 'holdings')
            ->assertSee('Acme')
            ->assertDontSee('Other');
    }

    public function test_sites_can_be_filtered_by_client_status_and_cms_and_sorted_by_client_name(): void
    {
        $manager = User::factory()->manager()->create();
        $zed = Client::factory()->create(['name' => 'Zed Agency']);
        $abe = Client::factory()->create(['name' => 'Abe Bakery']);
        Site::factory()->create(['client_id' => $zed->id, 'name' => 'Zed Site', 'cms_type' => 'wordpress']);
        Site::factory()->create(['client_id' => $abe->id, 'name' => 'Abe Site', 'cms_type' => 'craft']);
        Site::factory()->inactive()->create(['client_id' => $abe->id, 'name' => 'Abe Old Site', 'cms_type' => 'craft']);

        $component = Livewire::actingAs($manager)->test(SitesIndex::class)->call('sortBy', 'client');
        $component->assertSeeInOrder(['Abe Site', 'Zed Site']);

        $component
            ->set('client', $abe->id)
            ->assertSee('Abe Site')
            ->assertDontSee('Zed Site')
            ->call('setStatus', 'inactive')
            ->assertSee('Abe Old Site')
            ->assertDontSee('Abe Site')
            ->call('setStatus', 'all')
            ->set('client', null)
            ->set('cms', 'wordpress')
            ->assertSee('Zed Site')
            ->assertDontSee('Abe Site');
    }

    public function test_reports_can_be_searched_and_filtered_by_status_site_and_client(): void
    {
        $manager = User::factory()->manager()->create();
        $siteA = Site::factory()->create(['name' => 'Alpha Site']);
        $siteB = Site::factory()->create(['name' => 'Beta Site']);
        Report::factory()->create(['site_id' => $siteA->id, 'title' => 'August roundup', 'status' => 'final']);
        Report::factory()->create(['site_id' => $siteB->id, 'title' => 'September draft', 'status' => 'draft']);

        Livewire::actingAs($manager)->test(ReportsIndex::class)
            ->set('search', 'august')
            ->assertSee('August roundup')
            ->assertDontSee('September draft')
            ->set('search', '')
            ->call('setStatus', 'draft')
            ->assertSee('September draft')
            ->assertDontSee('August roundup')
            ->call('setStatus', 'all')
            ->set('site', $siteA->id)
            ->assertSee('August roundup')
            ->assertDontSee('September draft')
            ->set('site', null)
            ->set('client', $siteB->client_id)
            ->assertSee('September draft')
            ->assertDontSee('August roundup');
    }

    public function test_reports_index_honours_site_and_client_query_parameters(): void
    {
        $manager = User::factory()->manager()->create();
        $siteA = Site::factory()->create(['name' => 'Alpha Site']);
        $siteB = Site::factory()->create(['name' => 'Beta Site']);
        Report::factory()->create(['site_id' => $siteA->id, 'title' => 'Alpha report']);
        Report::factory()->create(['site_id' => $siteB->id, 'title' => 'Beta report']);

        $this->actingAs($manager)->get('/reports?site='.$siteA->id)
            ->assertOk()
            ->assertSee('Alpha report')
            ->assertDontSee('Beta report');

        $this->actingAs($manager)->get('/reports?client='.$siteB->client_id)
            ->assertOk()
            ->assertSee('Beta report')
            ->assertDontSee('Alpha report');
    }

    public function test_users_are_paginated_searchable_and_filterable_by_role(): void
    {
        $admin = User::factory()->administrator()->create(['name' => 'Admin Person']);
        User::factory()->count(30)->viewer()->create();
        User::factory()->manager()->create(['name' => 'Morgan Manager', 'email' => 'morgan@agency.test']);

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->assertSee('Admin Person')
            ->assertSeeHtml('aria-label="Pagination"')
            ->set('search', 'morgan@')
            ->assertSee('Morgan Manager')
            ->assertDontSee('Admin Person')
            ->set('search', '')
            ->set('role', 'manager')
            ->assertSee('Morgan Manager')
            ->assertDontSee('Admin Person');
    }

    public function test_templates_show_how_many_sites_use_them_and_can_be_duplicated(): void
    {
        $manager = User::factory()->manager()->create();
        $template = ReportTemplate::query()->create(['name' => 'House style', 'description' => 'Standard', 'blocks' => [['type' => 'cover']]]);
        Site::factory()->count(2)->create(['report_template_id' => $template->id, 'report_frequency' => 'monthly']);

        Livewire::actingAs($manager)->test(TemplatesIndex::class)
            ->assertSee('2 scheduled sites')
            ->call('duplicate', $template->id)
            ->assertDispatched('toast')
            ->assertSee('House style (copy)');

        $this->assertDatabaseHas('report_templates', ['name' => 'House style (copy)', 'description' => 'Standard']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'report_template.duplicated']);
    }

    public function test_search_and_sort_state_live_in_the_url(): void
    {
        $manager = User::factory()->manager()->create();
        Client::factory()->create(['name' => 'Zulu']);
        Client::factory()->create(['name' => 'Alpha']);

        $this->actingAs($manager)->get('/clients?q=zulu&sort=name&direction=desc')
            ->assertOk()
            ->assertSee('Zulu')
            ->assertDontSee('Alpha');
    }
}
