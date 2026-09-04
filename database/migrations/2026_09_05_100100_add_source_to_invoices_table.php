<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguishes a manually-entered invoice from one synced in from a billing
 * connection, and carries the provider's own id so a sync can upsert
 * idempotently instead of duplicating invoices on every run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('source')->default('manual')->after('client_id');
            $table->string('external_id')->nullable()->after('source');

            $table->unique(['client_id', 'source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'source', 'external_id']);
            $table->dropColumn(['source', 'external_id']);
        });
    }
};
