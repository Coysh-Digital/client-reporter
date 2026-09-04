<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_integration_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('value', 20, 4)->default(0);
            $table->string('unit')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('captured_at')->nullable();

            // One value per metric per period per connection; re-collection upserts.
            $table->unique(['site_integration_id', 'metric_key', 'period_start', 'period_end'], 'metrics_unique_period');
            $table->index(['site_integration_id', 'metric_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
