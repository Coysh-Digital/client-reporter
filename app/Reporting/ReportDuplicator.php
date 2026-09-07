<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Models\Report;
use App\Models\ReportBlock;
use Illuminate\Support\Facades\DB;

/**
 * Copies a report — its sections, their headings, options and hand-written
 * commentary — into a fresh draft for the same site. Handy for re-running a
 * bespoke report for another period: duplicate it, then change the date range
 * and generate. The copy is never generated and carries no frozen render,
 * period-specific AI summaries or share links from the original.
 */
class ReportDuplicator
{
    public function duplicate(Report $report, ?int $createdBy = null): Report
    {
        return DB::transaction(function () use ($report, $createdBy): Report {
            $copy = Report::query()->create([
                'site_id' => $report->site_id,
                'report_template_id' => $report->report_template_id,
                'title' => 'Copy of '.$report->title,
                'range_start' => $report->range_start,
                'range_end' => $report->range_end,
                'compare_previous' => $report->compare_previous,
                'intro' => $report->intro,
                'status' => 'draft',
                'scheduled' => false,
                'created_by' => $createdBy,
            ]);

            $report->blocks()->orderBy('position')->get()->each(
                fn (ReportBlock $block) => $copy->blocks()->create([
                    'type' => $block->type,
                    'position' => $block->position,
                    'heading' => $block->heading,
                    'config' => $block->config,
                    'commentary' => $block->commentary,
                    'is_hidden' => $block->is_hidden,
                    // ai_summary is frozen per period, so it's regenerated rather than copied.
                ]),
            );

            return $copy;
        });
    }
}
