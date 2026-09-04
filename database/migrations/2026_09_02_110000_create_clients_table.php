<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('company')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // Note: users.client_id (created earlier) intentionally has no database
        // foreign key. Adding one to an existing table is not portable to SQLite,
        // a first-class target for shared hosting. The relationship is enforced
        // at the application layer; deleting a client detaches its portal users.
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
