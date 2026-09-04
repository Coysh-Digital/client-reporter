<?php

declare(strict_types=1);

namespace App\Support\Branding;

use App\Models\BrandingProfile;
use App\Models\Client;
use App\Models\Site;
use App\Support\Settings;

/**
 * Resolves the effective branding for a client-facing surface by cascading
 * overrides: global agency branding is the base, then the Client's overrides,
 * then the Site's, then (later) a Report's. Only non-empty values override.
 */
class BrandingResolver
{
    private const DEFAULT_PRIMARY = '#33406b';

    private const DEFAULT_SECONDARY = '#8a6a2c';

    private const DEFAULT_HEADING_FONT = "'Source Serif 4', Georgia, 'Times New Roman', serif";

    private const DEFAULT_BODY_FONT = "'Hanken Grotesk', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

    private const DEFAULT_COVER_STYLE = 'standard';

    /**
     * The single global agency branding profile (created on first access).
     */
    public function global(): BrandingProfile
    {
        return BrandingProfile::query()
            ->whereNull('brandable_type')
            ->whereNull('brandable_id')
            ->firstOrCreate([]);
    }

    public function forClient(Client $client): ResolvedBranding
    {
        return $this->resolve([
            $this->global(),
            $client->branding,
        ]);
    }

    public function forSite(Site $site): ResolvedBranding
    {
        $site->loadMissing('client');

        return $this->resolve([
            $this->global(),
            $site->client->branding,
            $site->branding,
        ]);
    }

    /**
     * Merge an ordered list of profiles (base first) into a resolved value.
     *
     * @param  array<int, BrandingProfile|null>  $profiles
     */
    public function resolve(array $profiles): ResolvedBranding
    {
        $profiles = array_values(array_filter($profiles));

        $pick = fn (string $field): ?string => $this->firstNonEmpty($profiles, $field);
        $logo = $this->firstProfileWith($profiles, 'logo_path');
        $favicon = $this->firstProfileWith($profiles, 'favicon_path');

        return new ResolvedBranding(
            agencyName: $pick('agency_name') ?? config('client-reporter.name', 'Client Reporter'),
            tagline: $pick('tagline'),
            logoUrl: $logo?->logoUrl(),
            faviconUrl: $favicon?->faviconUrl(),
            primaryColor: $pick('primary_color') ?? self::DEFAULT_PRIMARY,
            secondaryColor: $pick('secondary_color') ?? self::DEFAULT_SECONDARY,
            website: $pick('website'),
            email: $pick('email'),
            phone: $pick('phone'),
            address: $pick('address'),
            reportFooter: $pick('report_footer'),
            emailFooter: $pick('email_footer'),
            reportCoverStyle: $pick('report_cover_style') ?? self::DEFAULT_COVER_STYLE,
            headingFont: $pick('heading_font') ?? self::DEFAULT_HEADING_FONT,
            bodyFont: $pick('body_font') ?? self::DEFAULT_BODY_FONT,
            customCss: $pick('custom_css'),
            aiSummaryLabel: $this->aiSummaryLabel(),
        );
    }

    /**
     * The label shown on AI-written summaries in reports. Agencies can rename it
     * (e.g. "Bolt Summary") from the AI settings screen; defaults to "AI summary".
     */
    private function aiSummaryLabel(): string
    {
        $label = trim((string) app(Settings::class)->get('ai.summary_label', ''));

        return $label !== '' ? $label : 'AI summary';
    }

    /**
     * @param  array<int, BrandingProfile>  $profiles
     */
    private function firstNonEmpty(array $profiles, string $field): ?string
    {
        foreach (array_reverse($profiles) as $profile) {
            $value = $profile->{$field};

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, BrandingProfile>  $profiles
     */
    private function firstProfileWith(array $profiles, string $field): ?BrandingProfile
    {
        foreach (array_reverse($profiles) as $profile) {
            if (! empty($profile->{$field})) {
                return $profile;
            }
        }

        return null;
    }
}
