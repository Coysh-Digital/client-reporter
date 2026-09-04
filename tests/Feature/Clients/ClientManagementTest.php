<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Livewire\Clients\Form;
use App\Livewire\Clients\Index;
use App\Models\Client;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_can_create_a_client(): void
    {
        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(Form::class)
            ->set('name', 'Smith & Co')
            ->set('contact_email', 'jane@smith.test')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clients', ['name' => 'Smith & Co']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'client.created']);
    }

    public function test_client_name_is_required(): void
    {
        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(Form::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_a_viewer_cannot_reach_the_create_route(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/clients/create')->assertForbidden();
    }

    public function test_a_viewer_can_list_clients(): void
    {
        $viewer = User::factory()->viewer()->create();
        Client::factory()->create(['name' => 'Visible Client']);

        $this->actingAs($viewer)->get('/clients')->assertOk()->assertSee('Visible Client');
    }

    public function test_deleting_a_client_cascades_to_its_sites(): void
    {
        $manager = User::factory()->manager()->create();
        $client = Client::factory()->create();
        $site = Site::factory()->for($client)->create();

        Livewire::actingAs($manager)->test(Index::class)
            ->call('delete', $client->id);

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }

    public function test_the_client_page_shows_report_history_and_a_site_summary(): void
    {
        $viewer = User::factory()->viewer()->create();
        $client = Client::factory()->create();
        $site = Site::factory()->for($client)->create(['name' => 'Northwind Site']);

        // A generated + sent report, and a draft.
        $sent = Report::factory()->for($site)->create(['status' => 'final', 'generated_at' => now()]);
        $sent->shares()->create(['token_hash' => hash('sha256', 'tok'), 'created_by' => null]);
        Report::factory()->for($site)->create(['status' => 'draft', 'generated_at' => null]);

        $this->actingAs($viewer)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Report history')
            ->assertSee('Northwind Site')
            ->assertSee('Reports generated')
            ->assertSee('Sent')
            ->assertSee('Draft');
    }

    public function test_search_filters_the_client_list(): void
    {
        $manager = User::factory()->manager()->create();
        Client::factory()->create(['name' => 'Alpha Agency']);
        Client::factory()->create(['name' => 'Beta Business']);

        Livewire::actingAs($manager)->test(Index::class)
            ->set('search', 'Alpha')
            ->assertSee('Alpha Agency')
            ->assertDontSee('Beta Business');
    }
}
