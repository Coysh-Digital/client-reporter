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
            // How many days after a reporting period closes to generate its
            // scheduled report (0 = as soon as it closes). Lets data settle —
            // e.g. 4 generates a monthly report on the 5th.
            $table->unsignedTinyInteger('report_generation_delay_days')->default(0)->after('auto_send');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn('report_generation_delay_days');
        });
    }
};
