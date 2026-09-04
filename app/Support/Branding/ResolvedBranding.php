<?php

declare(strict_types=1);

namespace App\Support\Branding;

/**
 * The fully-resolved, ready-to-render branding for a client-facing surface
 * (report, share page, email). Every field is populated: overrides have already
 * cascaded and sensible defaults fill any gaps, so views never need fallbacks.
 */
readonly class ResolvedBranding
{
    public function __construct(
        public string $agencyName,
        public ?string $tagline,
        public ?string $logoUrl,
        public ?string $faviconUrl,
        public string $primaryColor,
        public string $secondaryColor,
        public ?string $website,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $reportFooter,
        public ?string $emailFooter,
        public string $reportCoverStyle,
        public string $headingFont,
        public string $bodyFont,
        public ?string $customCss,
        public string $aiSummaryLabel = 'AI summary',
    ) {}

    public function hasLogo(): bool
    {
        return $this->logoUrl !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'agencyName' => $this->agencyName,
            'tagline' => $this->tagline,
            'logoUrl' => $this->logoUrl,
            'faviconUrl' => $this->faviconUrl,
            'primaryColor' => $this->primaryColor,
            'secondaryColor' => $this->secondaryColor,
            'website' => $this->website,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'reportFooter' => $this->reportFooter,
            'emailFooter' => $this->emailFooter,
            'reportCoverStyle' => $this->reportCoverStyle,
            'headingFont' => $this->headingFont,
            'bodyFont' => $this->bodyFont,
            'customCss' => $this->customCss,
            'aiSummaryLabel' => $this->aiSummaryLabel,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            agencyName: (string) ($data['agencyName'] ?? 'Report'),
            tagline: $data['tagline'] ?? null,
            logoUrl: $data['logoUrl'] ?? null,
            faviconUrl: $data['faviconUrl'] ?? null,
            primaryColor: (string) ($data['primaryColor'] ?? '#33406b'),
            secondaryColor: (string) ($data['secondaryColor'] ?? '#8a6a2c'),
            website: $data['website'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            reportFooter: $data['reportFooter'] ?? null,
            emailFooter: $data['emailFooter'] ?? null,
            reportCoverStyle: (string) ($data['reportCoverStyle'] ?? 'standard'),
            headingFont: (string) ($data['headingFont'] ?? "'Source Serif 4', Georgia, serif"),
            bodyFont: (string) ($data['bodyFont'] ?? "'Hanken Grotesk', sans-serif"),
            customCss: $data['customCss'] ?? null,
            aiSummaryLabel: (string) ($data['aiSummaryLabel'] ?? 'AI summary'),
        );
    }

    /**
     * CSS custom properties for injecting the palette/typography into a
     * client-facing report or email shell.
     *
     * @return array<string, string>
     */
    public function cssVariables(): array
    {
        return [
            '--brand-primary' => $this->primaryColor,
            '--brand-secondary' => $this->secondaryColor,
            '--brand-heading-font' => $this->headingFont,
            '--brand-body-font' => $this->bodyFont,
        ];
    }

    public function cssVariableString(): string
    {
        return collect($this->cssVariables())
            ->map(fn (string $value, string $key): string => "{$key}: {$value};")
            ->implode(' ');
    }
}
