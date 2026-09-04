<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Mail\ReportMail;
use App\Models\Report;
use App\Models\ReportShare;
use App\Reporting\ReportDocument;
use App\Reporting\ReportShareService;
use App\Support\AuditLogger;
use App\Support\Branding\BrandingResolver;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Sharing and delivery for a generated report: secure public links (with expiry
 * and optional password) and branded email with an optional PDF attachment.
 */
class SharePanel extends Component
{
    public Report $report;

    public ?int $expiryDays = null;

    public string $password = '';

    public ?string $newLink = null;

    public string $emailTo = '';

    public string $emailMessage = '';

    public bool $attachPdf = true;

    public function mount(Report $report): void
    {
        $this->report = $report;
        $this->emailTo = (string) $report->site->client->contact_email;

        $default = app(Settings::class)->get('default_share_expiry_days', config('client-reporter.reports.default_share_expiry_days'));
        $this->expiryDays = $default !== null ? (int) $default : null;
    }

    public function createLink(ReportShareService $shares, AuditLogger $audit): void
    {
        $this->authorize('manage-reports');

        if (! $this->reportIsGenerated()) {
            return;
        }

        $this->validate([
            'expiryDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
        ]);

        $result = $shares->create($this->report, $this->expiryDays, $this->password ?: null);
        $this->newLink = $result['token'] ? $shares->url($result['token']) : null;
        $this->password = '';

        $audit->log('report.shared', $this->report);
    }

    public function revoke(int $shareId): void
    {
        $this->authorize('manage-reports');

        $this->report->shares()->whereKey($shareId)->update(['revoked_at' => now()]);
    }

    public function sendEmail(ReportShareService $shares, BrandingResolver $branding, ReportDocument $document, AuditLogger $audit): void
    {
        $this->authorize('manage-reports');

        if (! $this->reportIsGenerated()) {
            return;
        }

        $this->validate([
            'emailTo' => ['required', 'email'],
            'emailMessage' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $shares->create($this->report);
        $resolved = $branding->forSite($this->report->site);

        $pdfPath = null;
        if ($this->attachPdf) {
            $pdfPath = $this->renderPdf($document);
        }

        Mail::to($this->emailTo)->send(new ReportMail(
            report: $this->report,
            url: $shares->url($result['token']),
            branding: $resolved,
            customMessage: $this->emailMessage ?: null,
            pdfPath: $pdfPath,
        ));

        if ($pdfPath !== null && file_exists($pdfPath)) {
            @unlink($pdfPath);
        }

        $audit->log('report.emailed', $this->report, metadata: ['to' => $this->emailTo]);
        session()->flash('share_status', 'Report emailed to '.$this->emailTo.'.');
        $this->emailMessage = '';
    }

    private function renderPdf(ReportDocument $document): ?string
    {
        $render = $this->report->latestRender;
        if ($render === null) {
            return null;
        }

        Storage::disk('local')->makeDirectory('tmp');
        $path = Storage::disk('local')->path('tmp/report-'.$this->report->id.'-'.uniqid().'.pdf');

        Pdf::view('reports.document', $document->fromRender($render))
            ->driver(app(Settings::class)->get('pdf_driver', config('client-reporter.pdf.driver', 'dompdf')))
            ->save($path);

        return $path;
    }

    private function reportIsGenerated(): bool
    {
        if ($this->report->isGenerated()) {
            return true;
        }

        $this->addError('generate', 'Generate the report before sharing or sending it.');

        return false;
    }

    /**
     * @return Collection<int, ReportShare>
     */
    public function activeShares(): Collection
    {
        return $this->report->shares()->whereNull('revoked_at')->latest()->get()
            ->filter(fn ($share) => ! $share->isExpired())
            ->values();
    }

    public function render(): mixed
    {
        return view('livewire.reports.share-panel', ['shares' => $this->activeShares()]);
    }
}
