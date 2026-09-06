<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Models\Report;
use App\Support\Settings;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Turns a report's frozen render into a PDF with the configured driver —
 * dompdf by default so it works on any shared host with no binaries; VPS
 * users may switch to Browsershot for pixel-perfect output.
 */
class ReportPdf
{
    public function __construct(
        private readonly ReportDocument $document,
        private readonly Settings $settings,
    ) {}

    /**
     * A download response for the report's latest frozen render, or null when
     * it has not been generated yet.
     */
    public function download(Report $report): ?PdfBuilder
    {
        $render = $report->latestRender;

        if ($render === null) {
            return null;
        }

        return Pdf::view('reports.document', $this->document->fromRender($render))
            ->driver($this->driver())
            ->name(Str::slug($report->title).'.pdf')
            ->download();
    }

    public function driver(): string
    {
        return (string) $this->settings->get('pdf_driver', config('client-reporter.pdf.driver', 'dompdf'));
    }
}
