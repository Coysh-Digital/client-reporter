<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Models\Report;
use App\Support\Branding\ResolvedBranding;
use App\Support\MergeTags;
use App\Support\Settings;

/**
 * Resolves the subject and body for a report email, cascading the site's own
 * override over the workspace default over a built-in fallback, then filling in
 * merge tags ({@see MergeTags}). One place so the mailer and the settings
 * preview agree.
 */
class ReportEmailContent
{
    public function __construct(private readonly Settings $settings) {}

    public function subject(Report $report, ResolvedBranding $branding): string
    {
        $template = $this->firstFilled(
            $report->site->email_subject,
            (string) $this->settings->get('report.email_subject', ''),
            $report->title,
        );

        return MergeTags::apply($template, $report, $branding) ?? $report->title;
    }

    /**
     * The body message, or null to fall back to the email's default paragraph.
     */
    public function body(Report $report, ResolvedBranding $branding): ?string
    {
        $template = $this->firstFilled(
            $report->site->email_body,
            (string) $this->settings->get('report.email_body', ''),
        );

        return $template === '' ? null : MergeTags::apply($template, $report, $branding);
    }

    private function firstFilled(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== null && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '';
    }
}
