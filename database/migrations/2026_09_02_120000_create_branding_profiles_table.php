<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branding_profiles', function (Blueprint $table) {
            $table->id();
            // Null morph = the global agency branding. Otherwise attaches to a
            // Client or Site as an override. Report-level overrides are added
            // with the reporting engine.
            $table->nullableMorphs('brandable');

            $table->string('agency_name')->nullable();
            $table->string('tagline')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();

            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();

            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();

            $table->text('report_footer')->nullable();
            $table->text('email_footer')->nullable();
            $table->string('report_cover_style')->nullable();
            $table->string('heading_font')->nullable();
            $table->string('body_font')->nullable();
            $table->text('custom_css')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_profiles');
    }
};
