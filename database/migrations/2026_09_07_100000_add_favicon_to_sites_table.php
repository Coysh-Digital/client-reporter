<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('favicon_path')->nullable()->after('url');
            $table->timestamp('favicon_fetched_at')->nullable()->after('favicon_path');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['favicon_path', 'favicon_fetched_at']);
        });
    }
};
