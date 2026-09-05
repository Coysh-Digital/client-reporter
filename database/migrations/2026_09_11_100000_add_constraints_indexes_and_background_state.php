<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tightens the schema now that the shape has settled: real foreign keys where
 * `nullOnDelete()` had silently been a no-op, one connection per integration
 * per site, indexes on the columns the dashboard and activity screens filter
 * on, and the columns background generation/collection report through.
 *
 * SQLite cannot add a foreign key to an existing table (Laravel's grammar
 * treats the command as a no-op), so the constraints apply on MySQL/MariaDB
 * and PostgreSQL; SQLite installs rely on the application-level cleanup.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dedupeSiteIntegrations();

        Schema::table('site_integrations', function (Blueprint $table): void {
            $table->unique(['site_id', 'integration_key']);
            // What the last failed collection was: auth | rate_limit | failed.
            $table->string('last_failure_kind', 20)->nullable()->after('last_error');
            // Set when a collection job is dispatched; cleared when it starts.
            $table->timestamp('collection_queued_at')->nullable()->after('last_attempted_at');
        });

        Schema::table('workspace_integrations', function (Blueprint $table): void {
            $table->timestamp('last_attempted_at')->nullable()->after('last_collected_at');
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->string('generation_status', 20)->nullable()->index()->after('status');
            $table->timestamp('generation_queued_at')->nullable()->after('generated_at');
            $table->timestamp('generation_started_at')->nullable()->after('generation_queued_at');
            $table->text('generation_error')->nullable()->after('generation_started_at');
            $table->index(['site_id', 'range_start', 'range_end']);
            $table->index(['scheduled', 'generated_at']);
        });

        Schema::table('collector_runs', function (Blueprint $table): void {
            $table->index(['status', 'finished_at']);
            $table->index('started_at');
        });

        Schema::table('metric_snapshots', function (Blueprint $table): void {
            $table->boolean('has_timeseries')->default(false)->after('granularity');
        });
        $this->backfillTimeseriesFlag();

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('reports', function (Blueprint $table): void {
                $table->foreign('report_template_id')->references('id')->on('report_templates')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
            Schema::table('report_shares', function (Blueprint $table): void {
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('report_shares', function (Blueprint $table): void {
                $table->dropForeign(['created_by']);
            });
            Schema::table('reports', function (Blueprint $table): void {
                $table->dropForeign(['report_template_id']);
                $table->dropForeign(['created_by']);
            });
        }

        Schema::table('metric_snapshots', function (Blueprint $table): void {
            $table->dropColumn('has_timeseries');
        });

        Schema::table('collector_runs', function (Blueprint $table): void {
            $table->dropIndex(['status', 'finished_at']);
            $table->dropIndex(['started_at']);
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->dropIndex(['site_id', 'range_start', 'range_end']);
            $table->dropIndex(['scheduled', 'generated_at']);
            $table->dropIndex(['generation_status']);
            $table->dropColumn(['generation_status', 'generation_queued_at', 'generation_started_at', 'generation_error']);
        });

        Schema::table('workspace_integrations', function (Blueprint $table): void {
            $table->dropColumn('last_attempted_at');
        });

        Schema::table('site_integrations', function (Blueprint $table): void {
            $table->dropUnique(['site_id', 'integration_key']);
            $table->dropColumn(['last_failure_kind', 'collection_queued_at']);
        });
    }

    /**
     * Keep the newest connection per (site, integration) so the unique index
     * can be created; older duplicates were only ever reachable by accident.
     */
    private function dedupeSiteIntegrations(): void
    {
        $duplicates = DB::table('site_integrations')
            ->select('site_id', 'integration_key')
            ->groupBy('site_id', 'integration_key')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $keep = DB::table('site_integrations')
                ->where('site_id', $duplicate->site_id)
                ->where('integration_key', $duplicate->integration_key)
                ->orderByDesc('id')
                ->value('id');

            DB::table('site_integrations')
                ->where('site_id', $duplicate->site_id)
                ->where('integration_key', $duplicate->integration_key)
                ->where('id', '!=', $keep)
                ->delete();
        }
    }

    private function backfillTimeseriesFlag(): void
    {
        DB::table('metric_snapshots')->select('id', 'payload')->orderBy('id')->chunk(200, function ($rows): void {
            foreach ($rows as $row) {
                $payload = json_decode((string) $row->payload, true);

                if (is_array($payload) && ! empty($payload['timeseries'])) {
                    DB::table('metric_snapshots')->where('id', $row->id)->update(['has_timeseries' => true]);
                }
            }
        });
    }
};
