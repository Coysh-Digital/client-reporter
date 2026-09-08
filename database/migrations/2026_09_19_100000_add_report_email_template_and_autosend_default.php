<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            // Per-site overrides for the report email (blank = inherit the
            // workspace default). Merge tags are resolved when the report sends.
            $table->string('email_subject')->nullable()->after('auto_send');
            $table->text('email_body')->nullable()->after('email_subject');
        });

        // auto_send becomes tri-state: null = inherit the workspace default,
        // true/false = an explicit per-site choice.
        Schema::table('sites', function (Blueprint $table): void {
            $table->boolean('auto_send')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('sites')->whereNull('auto_send')->update(['auto_send' => false]);

        Schema::table('sites', function (Blueprint $table): void {
            $table->boolean('auto_send')->nullable(false)->default(false)->change();
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn(['email_subject', 'email_body']);
        });
    }
};
