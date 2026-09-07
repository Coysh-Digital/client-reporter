<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Billing\BillingSyncer;
use App\Enums\ConnectionStatus;
use App\Models\Client;
use App\Models\ClientBillingConnection;
use App\Models\WorkspaceIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingAutoDisableTest extends TestCase
{
    use RefreshDatabase;

    private function link(): ClientBillingConnection
    {
        $client = Client::factory()->create();
        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'xero',
            'name' => 'Xero',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['refresh_token' => 'rt'],
        ]);

        return ClientBillingConnection::query()->create([
            'client_id' => $client->id,
            'workspace_integration_id' => $workspace->id,
            'external_contact_id' => 'c-1',
            'external_contact_name' => 'Northwind Cafe',
        ]);
    }

    private function fakeFailingXero(): void
    {
        config(['services.xero.client_id' => 'id', 'services.xero.client_secret' => 'secret']);
        Http::fake(['*identity.xero.com/connect/token*' => Http::response('', 401)]);
    }

    private function fakeWorkingXero(): void
    {
        config(['services.xero.client_id' => 'id', 'services.xero.client_secret' => 'secret']);
        Http::fake([
            '*identity.xero.com/connect/token*' => Http::response(['access_token' => 'at']),
            '*api.xero.com/connections*' => Http::response([['tenantId' => 'tenant-1', 'tenantName' => 'CD']]),
            '*api.xero.com/api.xro/2.0/Invoices*' => Http::response(['Invoices' => []]),
        ]);
    }

    public function test_a_billing_link_is_disabled_after_repeated_failures_and_then_skipped(): void
    {
        config(['client-reporter.collection.failure_threshold' => 3]);
        $this->fakeFailingXero();
        $link = $this->link();
        $syncer = app(BillingSyncer::class);

        $syncer->syncAll();
        $syncer->syncAll();
        $this->assertSame(2, $link->refresh()->consecutive_failures);
        $this->assertNull($link->disabled_at);

        $syncer->syncAll(); // third failure crosses the threshold

        $link->refresh();
        $this->assertSame(3, $link->consecutive_failures);
        $this->assertNotNull($link->disabled_at);
        $this->assertNotNull($link->last_error);

        // Once disabled it is no longer attempted by the sweep.
        $result = $syncer->syncAll();
        $this->assertSame(3, $link->refresh()->consecutive_failures);
        $this->assertSame([], $result['failed']);
    }

    public function test_a_successful_sync_clears_the_failure_state(): void
    {
        config(['client-reporter.collection.failure_threshold' => 3]);
        $link = $this->link();
        $link->update(['consecutive_failures' => 2, 'last_error' => 'boom']);
        $this->fakeWorkingXero();

        app(BillingSyncer::class)->syncOne($link->fresh());

        $link->refresh();
        $this->assertSame(0, $link->consecutive_failures);
        $this->assertNull($link->last_error);
        $this->assertNull($link->disabled_at);
        $this->assertNotNull($link->last_synced_at);
    }
}
