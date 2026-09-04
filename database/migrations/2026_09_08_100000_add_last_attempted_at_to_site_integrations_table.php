<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records when collection was last *attempted* for a connection, regardless of
 * whether it succeeded. The "due" check keys off this rather than the
 * success-only `last_collected_at`, so a persistently failing connection backs
 * off to the normal interval instead of being retried on every scheduler tick.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_integrations', function (Blueprint $table) {
            $table->timestamp('last_attempted_at')->nullable()->after('last_collected_at');
        });
    }

    public function down(): void
    {
        Schema::table('site_integrations', function (Blueprint $table) {
            $table->dropColumn('last_attempted_at');
        });
    }
};
