<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Report;
use App\Reporting\ReportPdf;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Streams a report as a PDF for staff.
 */
class ReportPdfController
{
    public function __invoke(Report $report, ReportPdf $pdf): PdfBuilder
    {
        Gate::authorize('access-admin');

        return $pdf->download($report) ?? abort(404, 'This report has not been generated yet.');
    }
}
