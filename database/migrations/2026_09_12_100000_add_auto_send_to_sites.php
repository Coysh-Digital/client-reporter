<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            // When a scheduled report generates, email it to the client
            // automatically. Opt-in per site; only meaningful with a schedule.
            $table->boolean('auto_send')->default(false)->after('report_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn('auto_send');
        });
    }
};
