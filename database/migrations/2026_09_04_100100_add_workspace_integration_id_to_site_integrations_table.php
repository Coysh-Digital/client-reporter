<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When set, a site connection borrows its credentials from a workspace-wide
 * connection; the site row then holds only its per-site selection (which
 * monitor/property maps to this site).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_integrations', function (Blueprint $table) {
            $table->foreignId('workspace_integration_id')
                ->nullable()
                ->after('integration_key')
                ->constrained('workspace_integrations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_integrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_integration_id');
        });
    }
};
