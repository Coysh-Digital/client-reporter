<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('background_tasks', function (Blueprint $table): void {
            $table->id();
            // What kind of work: report | collection | billing | favicon.
            $table->string('kind', 32);
            // A stable key (e.g. "report:42") so re-queuing updates one row.
            $table->string('task_key')->nullable();
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('progress_current')->nullable();
            $table->unsignedInteger('progress_total')->nullable();
            $table->nullableMorphs('subject');
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique('task_key');
            $table->index(['status', 'updated_at']);
            $table->index(['kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('background_tasks');
    }
};
