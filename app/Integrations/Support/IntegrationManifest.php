<?php

declare(strict_types=1);

namespace App\Integrations\Support;

/**
 * Descriptive metadata an integration exposes to Client Reporter: how it is
 * named and grouped in the UI, how it authenticates, and (for companion CMS
 * connectors) which compatibility slug it maps to.
 */
readonly class IntegrationManifest
{
    /**
     * @param  string|null  $providedBy  The key of another integration this one
     *                                   rides on. When set, the catalog shows this as a capability of that
     *                                   integration (no separate connection) and routes "connect" to it — e.g.
     *                                   Craft Commerce is provided by the Craft CMS connection.
     */
    public function __construct(
        public string $key,
        public string $name,
        public IntegrationCategory $category,
        public AuthMethod $authMethod,
        public string $description = '',
        public ?string $icon = null,
        public string $version = '1.0.0',
        public ?string $connectorSlug = null,
        public ?string $providedBy = null,
    ) {}

    /**
     * The public asset URL for this integration's brand logo, or null when no
     * icon is declared or the file isn't present yet (callers fall back to a
     * letter avatar). Guarding on existence lets a manifest point at a logo
     * that can be dropped in later without showing a broken image meanwhile.
     */
    public function iconUrl(): ?string
    {
        if ($this->icon === null || ! is_file(public_path($this->icon))) {
            return null;
        }

        return asset($this->icon);
    }
}
