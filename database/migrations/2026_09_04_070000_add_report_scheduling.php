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
            $table->string('report_frequency')->default('none')->after('is_active');
            $table->foreignId('report_template_id')->nullable()->after('report_frequency')
                ->constrained('report_templates')->nullOnDelete();
        });

        Schema::table('reports', function (Blueprint $table): void {
            // Marks reports created by the scheduler, so the dashboard can
            // surface auto-generated-but-unsent ones without touching manual reports.
            $table->boolean('scheduled')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('report_template_id');
            $table->dropColumn('report_frequency');
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn('scheduled');
        });
    }
};
