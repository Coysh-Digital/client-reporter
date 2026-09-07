<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Enums\DeliveryTrigger;
use App\Models\Report;
use App\Models\ReportShare;
use App\Reporting\ReportSender;
use App\Reporting\ReportShareService;
use App\Support\AuditLogger;
use App\Support\SafeError;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Livewire\Component;
use Throwable;

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
            'password' => ['nullable', 'string', 'min:10', 'max:255'],
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

    public function sendEmail(ReportSender $sender): void
    {
        $this->authorize('manage-reports');

        if (! $this->reportIsGenerated()) {
            return;
        }

        $this->validate([
            'emailTo' => ['required', 'email'],
            'emailMessage' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $sender->send(
                report: $this->report,
                to: $this->emailTo,
                customMessage: $this->emailMessage ?: null,
                attachPdf: $this->attachPdf,
                trigger: DeliveryTrigger::Manual,
                actor: auth()->user(),
            );
        } catch (Throwable $e) {
            $this->dispatch('toast', message: 'The report could not be sent: '.SafeError::message($e, 'delivery failed'), type: 'error');

            return;
        }

        $this->dispatch('toast', message: 'Report emailed to '.$this->emailTo.'.', type: 'ok');
        $this->emailMessage = '';
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
