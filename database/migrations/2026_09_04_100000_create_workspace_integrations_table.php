<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A workspace-wide (account-level) connection to an integration, e.g. a single
 * UptimeRobot API key that covers every site. Site connections can draw their
 * credentials from one of these, with the provider's monitors/properties
 * auto-matched to sites by URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('integration_key')->unique();
            $table->string('name');
            $table->string('status')->default('not_connected')->index();
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_collected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_integrations');
    }
};
