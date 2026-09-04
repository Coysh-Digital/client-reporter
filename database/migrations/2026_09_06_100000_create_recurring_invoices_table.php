<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring invoice schedules synced from a billing connection (e.g. FreeAgent)
 * so the agency can see what's coming up. These are schedules, not raised
 * invoices, so they live apart from the invoice ledger and never feed reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('freeagent');
            $table->string('external_id');
            $table->string('reference')->nullable();
            $table->string('frequency')->nullable();
            $table->string('status')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->date('next_recurs_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'source', 'external_id']);
            $table->index(['client_id', 'next_recurs_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices');
    }
};
