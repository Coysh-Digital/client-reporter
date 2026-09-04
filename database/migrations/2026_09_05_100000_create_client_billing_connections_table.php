<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a client to a contact/customer on a workspace-wide billing/accounting
 * connection (FreeAgent, Xero) — the counterpart to the site→monitor mapping
 * used for other workspace integrations, but for clients rather than sites.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_billing_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_integration_id')->constrained()->cascadeOnDelete();
            $table->string('external_contact_id');
            $table->string('external_contact_name');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_billing_connections');
    }
};
