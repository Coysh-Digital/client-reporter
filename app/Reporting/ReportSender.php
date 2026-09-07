<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Enums\DeliveryTrigger;
use App\Mail\ReportMail;
use App\Models\Report;
use App\Models\ReportDelivery;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Branding\BrandingResolver;
use App\Support\SafeError;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

/**
 * Emails a generated report to a recipient and records the delivery. The one
 * place report sending lives, shared by the manual share panel and the
 * scheduler's auto-send, so both behave identically and both leave a history.
 */
class ReportSender
{
    public function __construct(
        private readonly ReportShareService $shares,
        private readonly BrandingResolver $branding,
        private readonly ReportDocument $document,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Send a generated report by email, recording a {@see ReportDelivery} for
     * the outcome. On failure the delivery is recorded and the exception is
     * re-thrown, so the caller can decide whether to surface or swallow it.
     */
    public function send(Report $report, string $to, ?string $customMessage, bool $attachPdf, DeliveryTrigger $trigger, ?User $actor = null): ReportDelivery
    {
        $pdfPath = $attachPdf ? $this->renderPdf($report) : null;

        try {
            $share = $this->shares->create($report);

            Mail::to($to)->send(new ReportMail(
                report: $report,
                url: $this->shares->url($share['token']),
                branding: $this->branding->forSite($report->site),
                customMessage: $customMessage,
                pdfPath: $pdfPath,
            ));
        } catch (Throwable $e) {
            $this->cleanup($pdfPath);
            $this->record($report, $to, $trigger, $pdfPath !== null, false, SafeError::message($e, 'The report could not be sent.'), $actor);

            throw $e;
        }

        $this->cleanup($pdfPath);
        $this->audit->log('report.emailed', $report, $actor, metadata: ['to' => $to, 'trigger' => $trigger->value]);

        return $this->record($report, $to, $trigger, $pdfPath !== null, true, null, $actor);
    }

    /**
     * Record a delivery that never left — e.g. a scheduled auto-send with no
     * client contact email to send to.
     */
    public function recordSkipped(Report $report, string $reason, DeliveryTrigger $trigger): ReportDelivery
    {
        return $this->record($report, '', $trigger, false, false, $reason, null);
    }

    private function record(Report $report, string $to, DeliveryTrigger $trigger, bool $includedPdf, bool $succeeded, ?string $error, ?User $actor): ReportDelivery
    {
        return $report->deliveries()->create([
            'recipient' => $to,
            'trigger' => $trigger,
            'included_pdf' => $includedPdf,
            'succeeded' => $succeeded,
            'error' => $error,
            'created_by' => $actor?->id,
        ]);
    }

    private function renderPdf(Report $report): ?string
    {
        $render = $report->latestRender;
        if ($render === null) {
            return null;
        }

        Storage::disk('local')->makeDirectory('tmp');
        $path = Storage::disk('local')->path('tmp/report-'.$report->id.'-'.uniqid().'.pdf');

        Pdf::view('reports.document', $this->document->fromRender($render))
            ->driver(app(ReportPdf::class)->driver())
            ->save($path);

        return $path;
    }

    private function cleanup(?string $path): void
    {
        if ($path !== null && file_exists($path)) {
            @unlink($path);
        }
    }
}
