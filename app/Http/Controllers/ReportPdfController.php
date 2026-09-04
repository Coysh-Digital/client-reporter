<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Report;
use App\Reporting\ReportDocument;
use App\Support\Settings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a report as a PDF. Uses the configured driver — dompdf by default so
 * it works on any shared host with no binaries; VPS users may switch to
 * Browsershot for pixel-perfect output.
 */
class ReportPdfController
{
    public function __invoke(Report $report, ReportDocument $document): PdfBuilder|Response
    {
        Gate::authorize('access-admin');

        $render = $report->latestRender;

        if ($render === null) {
            abort(404, 'This report has not been generated yet.');
        }

        return Pdf::view('reports.document', $document->fromRender($render))
            ->driver(app(Settings::class)->get('pdf_driver', config('client-reporter.pdf.driver', 'dompdf')))
            ->name(Str::slug($report->title).'.pdf')
            ->download();
    }
}
