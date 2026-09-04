<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_blocks', function (Blueprint $table) {
            // The AI-written summary draft for this block. Editable like
            // commentary, then frozen into the ReportRender at generate time.
            $table->text('ai_summary')->nullable()->after('commentary');
        });
    }

    public function down(): void
    {
        Schema::table('report_blocks', function (Blueprint $table) {
            $table->dropColumn('ai_summary');
        });
    }
};
