<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\FreeAgent\FreeAgentIntegration;
use App\Livewire\Integrations\WorkspaceSetup;
use App\Models\Client;
use App\Models\ClientBillingConnection;
use App\Models\RecurringInvoice;
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
            '*/recurring_invoices*' => Http::response(['recurring_invoices' => []]),
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

    private function link(): ClientBillingConnection
    {
        return ClientBillingConnection::query()->create([
            'client_id' => Client::factory()->create()->id,
            'workspace_integration_id' => $this->connectedWorkspace()->id,
            'external_contact_id' => 'https://api.freeagent.com/v2/contacts/1',
            'external_contact_name' => 'Northwind',
        ]);
    }

    public function test_recurring_invoices_are_synced_but_kept_out_of_the_invoice_ledger(): void
    {
        Http::fake([
            '*/token_endpoint' => Http::response(['access_token' => 'access-token']),
            '*/recurring_invoices*' => Http::response(['recurring_invoices' => [[
                'url' => 'https://api.freeagent.com/v2/recurring_invoices/1',
                'frequency' => 'Monthly',
                'recurring_status' => 'Active',
                'next_recurs_on' => '2026-10-01',
                'recurring_end_date' => '2027-10-01',
                'currency' => 'gbp',
                'total_value' => '480.0',
            ]]]),
            '*/invoices*' => Http::response(['invoices' => []]),
        ]);

        $link = $this->link();

        (new FreeAgentIntegration)->syncInvoices($link);

        $recurring = RecurringInvoice::query()->where('client_id', $link->client_id)->sole();
        $this->assertSame('Monthly', $recurring->frequency);
        $this->assertTrue($recurring->isActive());
        $this->assertSame('GBP', $recurring->currency);
        $this->assertSame('480.00', $recurring->amount);
        $this->assertSame('2026-10-01', $recurring->next_recurs_on->toDateString());

        // A schedule is not a raised invoice, so nothing lands in the ledger.
        $this->assertSame(0, $link->client->invoices()->count());
    }

    public function test_recurring_invoices_no_longer_returned_are_pruned(): void
    {
        Http::fake([
            '*/token_endpoint' => Http::response(['access_token' => 'access-token']),
            '*/invoices*' => Http::response(['invoices' => []]),
        ]);
        $link = $this->link();

        RecurringInvoice::query()->create([
            'client_id' => $link->client_id,
            'source' => 'freeagent',
            'external_id' => 'https://api.freeagent.com/v2/recurring_invoices/stale',
            'frequency' => 'Weekly',
            'status' => 'Active',
        ]);

        Http::fake([
            '*/token_endpoint' => Http::response(['access_token' => 'access-token']),
            '*/recurring_invoices*' => Http::response(['recurring_invoices' => [[
                'url' => 'https://api.freeagent.com/v2/recurring_invoices/1',
                'frequency' => 'Monthly',
                'recurring_status' => 'Active',
                'total_value' => '100.0',
            ]]]),
            '*/invoices*' => Http::response(['invoices' => []]),
        ]);

        (new FreeAgentIntegration)->syncInvoices($link);

        $this->assertSame(1, RecurringInvoice::query()->where('client_id', $link->client_id)->count());
        $this->assertNull(RecurringInvoice::query()->where('external_id', 'https://api.freeagent.com/v2/recurring_invoices/stale')->first());
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
