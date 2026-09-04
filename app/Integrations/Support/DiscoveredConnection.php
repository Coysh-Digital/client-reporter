<?php

declare(strict_types=1);

namespace App\Integrations\Support;

/**
 * One entity found on a workspace-wide connection that can be attached to a
 * site — an UptimeRobot monitor, a GA4 property, a Plausible domain. The
 * workspace connect flow matches these to sites by {@see $url} and writes
 * {@see $settings} onto the resulting per-site connection.
 */
readonly class DiscoveredConnection
{
    /**
     * @param  string  $externalId  The provider's own id for this entity.
     * @param  string  $label  Human label shown in the mapping UI.
     * @param  string|null  $url  The entity's website, used to auto-match a site
     *                            (site-mapping providers — see Integration::workspaceMapsTo()).
     * @param  array<string, mixed>  $settings  Per-site/per-client settings to
     *                                          store on the matched connection (e.g. ['monitors' => '779035']).
     * @param  string|null  $email  The entity's contact email, used to auto-match
     *                              a client (client-mapping providers — billing/accounting contacts).
     */
    public function __construct(
        public string $externalId,
        public string $label,
        public ?string $url,
        public array $settings = [],
        public ?string $email = null,
    ) {}
}
