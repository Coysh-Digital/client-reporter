<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_shares', function (Blueprint $table): void {
            // Wrong-password attempts against this link; the link is revoked
            // once they pass the ceiling in PublicReportController.
            $table->unsignedInteger('failed_unlocks')->default(0)->after('views');
        });
    }

    public function down(): void
    {
        Schema::table('report_shares', function (Blueprint $table): void {
            $table->dropColumn('failed_unlocks');
        });
    }
};
