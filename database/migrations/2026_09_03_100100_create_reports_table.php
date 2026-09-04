<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_template_id')->nullable()->nullOnDelete();
            $table->string('title');
            $table->date('range_start');
            $table->date('range_end');
            $table->boolean('compare_previous')->default(true);
            $table->string('status')->default('draft')->index();
            $table->text('intro')->nullable();
            $table->foreignId('created_by')->nullable()->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
