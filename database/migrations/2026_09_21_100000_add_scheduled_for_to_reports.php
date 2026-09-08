<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // A one-off date on which this draft should auto-generate. Null for
            // reports built by hand or driven by a site's recurring schedule.
            $table->date('scheduled_for')->nullable()->after('scheduled');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('scheduled_for');
        });
    }
};
