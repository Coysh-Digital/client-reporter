<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branding_profiles', function (Blueprint $table): void {
            // Extra report-cover (banner) customisation, all optional so they
            // cascade like every other branding field: a null inherits.
            $table->string('report_cover_label')->nullable()->after('report_cover_style');
            $table->string('report_cover_color')->nullable()->after('report_cover_label');
            $table->string('report_cover_image_path')->nullable()->after('report_cover_color');
            $table->boolean('report_cover_show_tagline')->nullable()->after('report_cover_image_path');
            $table->boolean('report_cover_show_period')->nullable()->after('report_cover_show_tagline');
            $table->boolean('report_cover_show_contact')->nullable()->after('report_cover_show_period');
        });
    }

    public function down(): void
    {
        Schema::table('branding_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'report_cover_label',
                'report_cover_color',
                'report_cover_image_path',
                'report_cover_show_tagline',
                'report_cover_show_period',
                'report_cover_show_contact',
            ]);
        });
    }
};
