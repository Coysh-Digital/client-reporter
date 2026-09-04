<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The agency's own billing of a client — manually entered, not pulled from an
 * external accounting API, so it works regardless of which invoicing tool the
 * agency actually uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->nullable();
            $table->string('status')->default('draft')->index();
            $table->date('issued_at');
            $table->date('due_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
