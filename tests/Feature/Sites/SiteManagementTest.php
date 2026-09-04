<?php

declare(strict_types=1);

namespace Tests\Feature\Sites;

use App\Livewire\Sites\Form;
use App\Livewire\Sites\Show;
use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_can_create_a_site_for_a_client(): void
    {
        $manager = User::factory()->manager()->create();
        $client = Client::factory()->create();

        Livewire::actingAs($manager)->test(Form::class)
            ->set('client_id', $client->id)
            ->set('url', 'https://example.com')
            ->set('name', 'Example')
            ->set('cms_type', 'wordpress')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sites', [
            'client_id' => $client->id,
            'url' => 'https://example.com',
            'cms_type' => 'wordpress',
        ]);
    }

    public function test_site_name_is_derived_from_the_url_when_blank(): void
    {
        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(Form::class)
            ->set('url', 'https://www.acme.co.uk')
            ->assertSet('name', 'acme.co.uk');
    }

    public function test_url_must_be_valid(): void
    {
        $manager = User::factory()->manager()->create();
        $client = Client::factory()->create();

        Livewire::actingAs($manager)->test(Form::class)
            ->set('client_id', $client->id)
            ->set('name', 'Bad')
            ->set('url', 'not-a-url')
            ->call('save')
            ->assertHasErrors('url');
    }

    public function test_a_client_is_required(): void
    {
        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(Form::class)
            ->set('url', 'https://example.com')
            ->set('name', 'Example')
            ->call('save')
            ->assertHasErrors('client_id');
    }

    public function test_deleting_a_site_returns_to_the_client(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        Livewire::actingAs($manager)->test(Show::class, ['site' => $site])
            ->call('delete')
            ->assertRedirect(route('clients.show', $site->client));

        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }

    public function test_a_viewer_cannot_reach_the_site_create_route(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/sites/create')->assertForbidden();
    }
}
