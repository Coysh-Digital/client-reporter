<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_integration_id')->constrained()->cascadeOnDelete();
            $table->string('collector_key');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('granularity')->default('range');
            // Integration-owned richer payload (top pages, product lists, incidents).
            $table->json('payload');
            $table->timestamp('captured_at')->nullable();

            $table->unique(['site_integration_id', 'collector_key', 'period_start', 'period_end'], 'snapshots_unique_period');
            $table->index(['site_integration_id', 'collector_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_snapshots');
    }
};
