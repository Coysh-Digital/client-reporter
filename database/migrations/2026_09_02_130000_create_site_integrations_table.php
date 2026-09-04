<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('integration_key')->index();
            $table->string('name');
            $table->string('status')->default('not_connected')->index();
            // Encrypted at the application layer (see SiteIntegration::casts()).
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->string('connector_version')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_collected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'integration_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_integrations');
    }
};
