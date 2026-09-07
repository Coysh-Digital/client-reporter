<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_integrations', function (Blueprint $table): void {
            // Consecutive failed collection passes; reset to 0 on any success.
            // When it crosses the configured threshold the connection is
            // auto-disabled (status = disabled) and skipped until reconnected.
            $table->unsignedInteger('consecutive_failures')->default(0)->after('last_failure_kind');
            $table->timestamp('disabled_at')->nullable()->after('consecutive_failures');
        });
    }

    public function down(): void
    {
        Schema::table('site_integrations', function (Blueprint $table): void {
            $table->dropColumn(['consecutive_failures', 'disabled_at']);
        });
    }
};
