<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\ConnectionStatus;
use App\Enums\InvoiceStatus;
use App\Integrations\FreeAgent\FreeAgentIntegration;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Livewire\Billing\InvoicePanel;
use App\Livewire\Integrations\WorkspaceSetup;
use App\Models\Client;
use App\Models\ClientBillingConnection;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class FreeAgentTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFreeAgent(): void
    {
        config(['services.freeagent.client_id' => 'id', 'services.freeagent.client_secret' => 'secret']);

        Http::fake([
            '*token_endpoint*' => Http::response(['access_token' => 'at']),
            '*api.freeagent.com/v2/contacts*' => Http::response(['contacts' => [
                ['url' => 'https://api.freeagent.com/v2/contacts/1', 'organisation_name' => 'Northwind Cafe', 'email' => 'billing@northwind.test'],
                ['url' => 'https://api.freeagent.com/v2/contacts/2', 'organisation_name' => 'Unmatched Ltd', 'email' => 'nobody@nowhere.test'],
            ]]),
            '*api.freeagent.com/v2/recurring_invoices*' => Http::response(['recurring_invoices' => []]),
            '*api.freeagent.com/v2/invoices*' => Http::response(['invoices' => [
                ['url' => 'https://api.freeagent.com/v2/invoices/9', 'reference' => 'INV-9', 'dated_on' => '2026-08-05', 'due_on' => '2026-08-19', 'paid_on' => '2026-08-10', 'total_value' => '1850.00', 'currency' => 'GBP', 'status' => 'Paid'],
                ['url' => 'https://api.freeagent.com/v2/invoices/10', 'reference' => 'INV-10', 'dated_on' => '2026-08-20', 'due_on' => '2026-07-01', 'total_value' => '2400.00', 'currency' => 'GBP', 'status' => 'Overdue'],
            ]]),
        ]);
    }

    public function test_freeagent_is_workspace_only_and_maps_to_clients(): void
    {
        $integration = new FreeAgentIntegration;

        $this->assertTrue($integration->supportsWorkspaceScope());
        $this->assertTrue($integration->onlyWorkspaceScope());
        $this->assertSame('client', $integration->workspaceMapsTo());
        $this->assertContains('freeagent', app(IntegrationRegistry::class)->keysInCategory(IntegrationCategory::Billing));
    }

    public function test_workspace_connect_discovers_and_matches_contacts_by_email(): void
    {
        $this->fakeFreeAgent();
        $manager = User::factory()->manager()->create();
        $northwind = Client::factory()->create(['name' => 'Northwind', 'contact_email' => 'billing@northwind.test']);

        // Simulate the OAuth callback having already stored a refresh token.
        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'freeagent', 'name' => 'FreeAgent',
            'status' => ConnectionStatus::Connected, 'credentials' => ['refresh_token' => 'rt'],
        ]);

        Livewire::actingAs($manager)->test(WorkspaceSetup::class, ['workspace' => $workspace])
            ->call('connect')
            ->assertSet('phase', 'mapping')
            ->assertSet('assignments.0', $northwind->id)
            ->assertSet('assignments.1', '')
            ->call('confirm')
            ->assertRedirect(route('integrations.index'));

        $link = ClientBillingConnection::query()->where('client_id', $northwind->id)->firstOrFail();
        $this->assertSame('https://api.freeagent.com/v2/contacts/1', $link->external_contact_id);
        $this->assertNotNull($link->last_synced_at);

        // The initial sync ran as part of confirming the mapping.
        $this->assertSame(2, Invoice::query()->where('client_id', $northwind->id)->count());
    }

    public function test_sync_invoices_upserts_and_maps_statuses(): void
    {
        $this->fakeFreeAgent();
        $client = Client::factory()->create();
        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'freeagent', 'name' => 'FreeAgent',
            'status' => ConnectionStatus::Connected, 'credentials' => ['refresh_token' => 'rt'],
        ]);
        $link = ClientBillingConnection::query()->create([
            'client_id' => $client->id, 'workspace_integration_id' => $workspace->id,
            'external_contact_id' => 'https://api.freeagent.com/v2/contacts/1',
            'external_contact_name' => 'Northwind Cafe',
        ]);

        $count = (new FreeAgentIntegration)->syncInvoices($link);
        $this->assertSame(2, $count);

        $paid = Invoice::query()->where('external_id', 'https://api.freeagent.com/v2/invoices/9')->firstOrFail();
        $this->assertSame(InvoiceStatus::Paid, $paid->status);
        $this->assertSame(1850.0, (float) $paid->amount);
        $this->assertSame('freeagent', $paid->source);
        $this->assertTrue($paid->isSynced());

        // FreeAgent's own "Overdue" label isn't specially mapped — it becomes
        // Sent, and Invoice::isOverdue() derives the same conclusion from the
        // due date, so the two never disagree.
        $overdue = Invoice::query()->where('external_id', 'https://api.freeagent.com/v2/invoices/10')->firstOrFail();
        $this->assertSame(InvoiceStatus::Sent, $overdue->status);
        $this->assertTrue($overdue->isOverdue());

        // Re-syncing does not duplicate rows.
        (new FreeAgentIntegration)->syncInvoices($link);
        $this->assertSame(2, Invoice::query()->where('client_id', $client->id)->count());
    }

    public function test_a_synced_invoice_cannot_be_edited_or_deleted_from_the_panel(): void
    {
        $manager = User::factory()->manager()->create();
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->for($client)->create(['source' => 'freeagent', 'external_id' => 'x1']);

        Livewire::actingAs($manager)->test(InvoicePanel::class, ['client' => $client])
            ->call('startEdit', $invoice->id)->assertForbidden();

        Livewire::actingAs($manager)->test(InvoicePanel::class, ['client' => $client])
            ->call('delete', $invoice->id)->assertForbidden();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }
}
