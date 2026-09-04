<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\ConnectionStatus;
use App\Enums\InvoiceStatus;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Xero\XeroClient;
use App\Integrations\Xero\XeroIntegration;
use App\Livewire\Integrations\WorkspaceSetup;
use App\Models\Client;
use App\Models\ClientBillingConnection;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class XeroTest extends TestCase
{
    use RefreshDatabase;

    private function fakeXero(): void
    {
        config(['services.xero.client_id' => 'id', 'services.xero.client_secret' => 'secret']);

        Http::fake([
            '*identity.xero.com/connect/token*' => Http::response(['access_token' => 'at']),
            '*api.xero.com/connections*' => Http::response([
                ['tenantId' => 'tenant-1', 'tenantName' => 'Coysh Digital'],
            ]),
            '*api.xero.com/api.xro/2.0/Contacts*' => Http::response(['Contacts' => [
                ['ContactID' => 'c-1', 'Name' => 'Northwind Cafe', 'EmailAddress' => 'billing@northwind.test'],
            ]]),
            '*api.xero.com/api.xro/2.0/Invoices*' => Http::response(['Invoices' => [
                [
                    'InvoiceID' => 'inv-1', 'InvoiceNumber' => 'INV-001', 'Type' => 'ACCREC',
                    'Status' => 'PAID', 'Total' => 1850.0, 'CurrencyCode' => 'GBP',
                    'Date' => '/Date(1722816000000+0000)/', 'DueDate' => '/Date(1724025600000+0000)/',
                    'FulllyPaidOnDate' => null, 'FullyPaidOnDate' => '/Date(1723248000000+0000)/',
                ],
                [
                    'InvoiceID' => 'inv-2', 'InvoiceNumber' => 'INV-002', 'Type' => 'ACCREC',
                    'Status' => 'AUTHORISED', 'Total' => 2400.0, 'CurrencyCode' => 'GBP',
                    'Date' => '/Date(1724112000000+0000)/', 'DueDate' => '/Date(1719792000000+0000)/',
                ],
            ]]),
        ]);
    }

    public function test_xero_date_parses_the_legacy_dotnet_json_format(): void
    {
        $parsed = XeroClient::parseDate('/Date(1722816000000+0000)/');

        $this->assertInstanceOf(CarbonImmutable::class, $parsed);
        $this->assertSame('2024-08-05', $parsed->toDateString());
        $this->assertNull(XeroClient::parseDate(null));
        $this->assertNull(XeroClient::parseDate('not a date'));
    }

    public function test_xero_is_workspace_only_and_maps_to_clients(): void
    {
        $integration = new XeroIntegration;

        $this->assertTrue($integration->supportsWorkspaceScope());
        $this->assertTrue($integration->onlyWorkspaceScope());
        $this->assertSame('client', $integration->workspaceMapsTo());
        $this->assertContains('xero', app(IntegrationRegistry::class)->keysInCategory(IntegrationCategory::Billing));
    }

    public function test_workspace_connect_picks_the_first_tenant_and_matches_by_email(): void
    {
        $this->fakeXero();
        $manager = User::factory()->manager()->create();
        $northwind = Client::factory()->create(['name' => 'Northwind', 'contact_email' => 'billing@northwind.test']);

        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'xero', 'name' => 'Xero',
            'status' => ConnectionStatus::Connected, 'credentials' => ['refresh_token' => 'rt'],
        ]);

        Livewire::actingAs($manager)->test(WorkspaceSetup::class, ['workspace' => $workspace])
            ->call('connect')
            ->assertSet('phase', 'mapping')
            ->assertSet('assignments.0', $northwind->id)
            ->call('confirm')
            ->assertRedirect(route('integrations.index'));

        $workspace->refresh();
        $this->assertSame('tenant-1', $workspace->setting('tenant_id'));

        $link = ClientBillingConnection::query()->where('client_id', $northwind->id)->firstOrFail();
        $this->assertSame('c-1', $link->external_contact_id);
        $this->assertSame(2, Invoice::query()->where('client_id', $northwind->id)->count());
    }

    public function test_sync_invoices_maps_statuses_and_dates(): void
    {
        $this->fakeXero();
        $client = Client::factory()->create();
        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'xero', 'name' => 'Xero',
            'status' => ConnectionStatus::Connected, 'credentials' => ['refresh_token' => 'rt'],
        ]);
        $link = ClientBillingConnection::query()->create([
            'client_id' => $client->id, 'workspace_integration_id' => $workspace->id,
            'external_contact_id' => 'c-1', 'external_contact_name' => 'Northwind Cafe',
        ]);

        $count = (new XeroIntegration)->syncInvoices($link);
        $this->assertSame(2, $count);

        $paid = Invoice::query()->where('external_id', 'inv-1')->firstOrFail();
        $this->assertSame(InvoiceStatus::Paid, $paid->status);
        $this->assertSame(1850.0, (float) $paid->amount);
        $this->assertSame('2024-08-05', $paid->issued_at->toDateString());
        $this->assertSame('xero', $paid->source);

        $authorised = Invoice::query()->where('external_id', 'inv-2')->firstOrFail();
        $this->assertSame(InvoiceStatus::Sent, $authorised->status);
        $this->assertTrue($authorised->isOverdue());

        // Re-syncing does not duplicate rows, and reuses the cached tenant id.
        (new XeroIntegration)->syncInvoices($link);
        $this->assertSame(2, Invoice::query()->where('client_id', $client->id)->count());
    }
}
