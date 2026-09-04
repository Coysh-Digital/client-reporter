<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_integration_id')->constrained()->cascadeOnDelete();
            $table->string('collector_key');
            $table->string('status')->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('records_written')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['site_integration_id', 'collector_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_runs');
    }
};
