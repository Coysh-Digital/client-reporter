<?php

declare(strict_types=1);

use App\Models\ReportBlock;
use App\Models\ReportTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * The standalone "Email campaigns" (email.campaigns) section has been merged
 * into the "Leads & signups" (forms.summary) section, which now shows the email
 * metrics and campaign table too. Rewrite any stored references so existing
 * templates and reports keep a single, consolidated Forms & Leads section:
 * drop the email.campaigns block where a forms.summary sibling already exists,
 * otherwise convert it to forms.summary in place.
 *
 * Irreversible: the email.campaigns block type no longer exists, so down() is a
 * no-op. Orphaned blocks would in any case resolve as unavailable and render
 * nothing, so nothing breaks even if a reference were somehow missed.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (ReportTemplate::all() as $template) {
            $blocks = $template->blocks ?? [];
            $hasSummary = collect($blocks)->contains(fn (array $b): bool => ($b['type'] ?? '') === 'forms.summary');
            $changed = false;
            $out = [];

            foreach ($blocks as $block) {
                if (($block['type'] ?? '') === 'email.campaigns') {
                    $changed = true;
                    if ($hasSummary) {
                        continue; // forms.summary already covers it — drop the duplicate.
                    }
                    $block['type'] = 'forms.summary';
                    $block['config'] = null; // let the merged block's defaults apply.
                    $hasSummary = true;
                }
                $out[] = $block;
            }

            if ($changed) {
                $template->update(['blocks' => $out]);
            }
        }

        $reportIds = ReportBlock::query()->where('type', 'email.campaigns')->distinct()->pluck('report_id');
        foreach ($reportIds as $reportId) {
            $hasSummary = ReportBlock::query()
                ->where('report_id', $reportId)
                ->where('type', 'forms.summary')
                ->exists();

            $emailBlocks = ReportBlock::query()
                ->where('report_id', $reportId)
                ->where('type', 'email.campaigns')
                ->orderBy('position')
                ->get();

            foreach ($emailBlocks as $block) {
                if ($hasSummary) {
                    $block->delete();

                    continue;
                }

                $block->update(['type' => 'forms.summary', 'config' => null]);
                $hasSummary = true;
            }
        }
    }

    public function down(): void
    {
        // No-op: the email.campaigns block type has been removed.
    }
};
