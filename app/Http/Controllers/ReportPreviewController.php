<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Report;
use App\Reporting\ReportDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Renders the branded, client-facing report document for staff preview. Uses the
 * frozen render when the report has been generated, otherwise resolves live so
 * the builder preview reflects unsaved work.
 */
class ReportPreviewController
{
    public function __invoke(Report $report, ReportDocument $document): View
    {
        Gate::authorize('access-admin');

        $report->load(['blocks', 'site.client', 'latestRender']);

        // The builder wants a live preview of the current blocks; the generated
        // view (?frozen=1) shows exactly what the client received.
        $useFrozen = request()->boolean('frozen') && $report->latestRender !== null;

        $payload = $useFrozen
            ? $document->fromRender($report->latestRender)
            : $document->live($report);

        return view('reports.document', $payload);
    }
}
