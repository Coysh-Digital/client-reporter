<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Report;
use App\Support\Branding\ResolvedBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A fully agency-branded email delivering a report link (and optional PDF).
 * The sender display name and all visible identity come from the resolved
 * branding, so nothing reveals Client Reporter.
 */
class ReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<int, string> */
    public array $pdfAttachments = [];

    public function __construct(
        public Report $report,
        public string $url,
        public ResolvedBranding $branding,
        public ?string $customMessage = null,
        ?string $pdfPath = null,
    ) {
        if ($pdfPath !== null) {
            $this->pdfAttachments[] = $pdfPath;
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->branding->agencyName),
            replyTo: $this->branding->email ? [new Address($this->branding->email, $this->branding->agencyName)] : [],
            subject: $this->report->title,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.report');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (string $path) => Attachment::fromPath($path)->withMime('application/pdf'),
            $this->pdfAttachments,
        );
    }
}
