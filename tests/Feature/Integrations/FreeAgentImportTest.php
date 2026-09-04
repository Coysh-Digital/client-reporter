<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Livewire\Integrations\WorkspaceSetup;
use App\Models\Client;
use App\Models\ClientBillingConnection;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class FreeAgentImportTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFreeAgent(): void
    {
        Http::fake([
            '*/token_endpoint' => Http::response(['access_token' => 'access-token']),
            '*/contacts*' => Http::response(['contacts' => [
                ['url' => 'https://api.freeagent.com/v2/contacts/1', 'organisation_name' => 'Northwind', 'email' => 'ap@northwind.test'],
                ['url' => 'https://api.freeagent.com/v2/contacts/2', 'organisation_name' => 'Acme', 'email' => 'ap@acme.test'],
            ]]),
            '*/invoices*' => Http::response(['invoices' => []]),
        ]);
    }

    private function connectedWorkspace(): WorkspaceIntegration
    {
        return WorkspaceIntegration::query()->create([
            'integration_key' => 'freeagent',
            'name' => 'FreeAgent (workspace)',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['refresh_token' => 'refresh-token'],
        ]);
    }

    public function test_contacts_are_fetched_100_per_page_to_get_past_the_25_default(): void
    {
        $this->fakeFreeAgent();
        $manager = User::factory()->manager()->create();
        $workspace = $this->connectedWorkspace();

        Livewire::actingAs($manager)->test(WorkspaceSetup::class, ['workspace' => $workspace])
            ->call('connect')
            ->assertSet('phase', 'mapping')
            ->assertCount('discovered', 2);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/contacts')
            && str_contains($request->url(), 'per_page=100'));
    }

    public function test_a_contact_can_be_imported_as_a_new_client(): void
    {
        $this->fakeFreeAgent();
        $manager = User::factory()->manager()->create();
        $workspace = $this->connectedWorkspace();

        Livewire::actingAs($manager)->test(WorkspaceSetup::class, ['workspace' => $workspace])
            ->call('connect')
            ->set('assignments.0', 'new')
            ->set('assignments.1', '')
            ->call('confirm')
            ->assertRedirect(route('integrations.index'));

        $client = Client::query()->firstWhere('name', 'Northwind');
        $this->assertNotNull($client);
        $this->assertSame('ap@northwind.test', $client->contact_email);

        $this->assertDatabaseHas('client_billing_connections', [
            'client_id' => $client->id,
            'external_contact_id' => 'https://api.freeagent.com/v2/contacts/1',
        ]);

        // The skipped contact created neither a client nor a connection.
        $this->assertNull(Client::query()->firstWhere('name', 'Acme'));
        $this->assertSame(1, ClientBillingConnection::query()->count());
    }

    public function test_create_new_for_unmapped_marks_every_unassigned_contact(): void
    {
        $this->fakeFreeAgent();
        $manager = User::factory()->manager()->create();
        $workspace = $this->connectedWorkspace();

        Livewire::actingAs($manager)->test(WorkspaceSetup::class, ['workspace' => $workspace])
            ->call('connect')
            ->call('createNewForUnmapped')
            ->assertSet('assignments.0', 'new')
            ->assertSet('assignments.1', 'new')
            ->call('confirm');

        $this->assertSame(2, Client::query()->count());
        $this->assertSame(2, ClientBillingConnection::query()->count());
    }
}
