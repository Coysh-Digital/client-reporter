<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_billing_connections', function (Blueprint $table): void {
            // Billing sync has no live/needs-attention status of its own, so it
            // tracks failures directly: consecutive failed syncs, the last
            // error, and a disabled marker set once the threshold is crossed.
            // A disabled link is skipped by the hourly sync until reconnected.
            $table->unsignedInteger('consecutive_failures')->default(0)->after('last_synced_at');
            $table->timestamp('last_attempted_at')->nullable()->after('consecutive_failures');
            $table->text('last_error')->nullable()->after('last_attempted_at');
            $table->timestamp('disabled_at')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('client_billing_connections', function (Blueprint $table): void {
            $table->dropColumn(['consecutive_failures', 'last_attempted_at', 'last_error', 'disabled_at']);
        });
    }
};
