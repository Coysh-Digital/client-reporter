<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Reporting\ReportPdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * A client downloading one of their own published reports as a PDF.
 */
class PortalReportPdfController
{
    public function __invoke(Report $report, ReportPdf $pdf): PdfBuilder
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($report->site?->client_id === $user->client_id, 403);
        abort_unless($report->isGenerated() && $report->status === 'final', 404);

        return $pdf->download($report) ?? abort(404);
    }
}
