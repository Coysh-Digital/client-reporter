<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Livewire\Billing\InvoicePanel;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoicePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_can_add_an_invoice(): void
    {
        $manager = User::factory()->manager()->create();
        $client = Client::factory()->create();

        Livewire::actingAs($manager)->test(InvoicePanel::class, ['client' => $client])
            ->call('startCreate')
            ->set('number', 'INV-000123')
            ->set('description', 'Monthly retainer')
            ->set('amount', '1850')
            ->set('currency', 'gbp')
            ->set('issued_at', '2026-08-01')
            ->set('due_at', '2026-08-15')
            ->call('save')
            ->assertSet('showForm', false);

        $invoice = Invoice::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertSame('INV-000123', $invoice->number);
        $this->assertSame('GBP', $invoice->currency);
        $this->assertSame(1850.00, (float) $invoice->amount);
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
    }

    public function test_marking_an_invoice_paid_stamps_the_paid_date(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'paid_at' => null]);

        Livewire::actingAs($manager)->test(InvoicePanel::class, ['client' => $invoice->client])
            ->call('markPaid', $invoice->id);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_a_viewer_cannot_add_or_delete_invoices(): void
    {
        $viewer = User::factory()->viewer()->create();
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->for($client)->create();

        Livewire::actingAs($viewer)->test(InvoicePanel::class, ['client' => $client])
            ->call('startCreate')->assertForbidden();

        Livewire::actingAs($viewer)->test(InvoicePanel::class, ['client' => $client])
            ->call('delete', $invoice->id)->assertForbidden();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_a_sent_invoice_past_its_due_date_is_derived_as_overdue(): void
    {
        $overdue = Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'due_at' => now()->subDay()]);
        $notYetDue = Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'due_at' => now()->addDay()]);
        $paidLate = Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'due_at' => now()->subDay()]);

        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($notYetDue->isOverdue());
        $this->assertFalse($paidLate->isOverdue());
    }
}
