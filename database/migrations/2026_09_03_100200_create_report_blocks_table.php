<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('position')->default(0);
            $table->string('heading')->nullable();
            $table->json('config')->nullable();
            $table->text('commentary')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->index(['report_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_blocks');
    }
};
