<?php

declare(strict_types=1);

namespace App\Importers;

/**
 * A WordPress site discovered on an external management platform, normalised to
 * the fields Client Reporter needs to create a Site. `suggestedClient` is the
 * platform's own grouping (where it has one), used to pre-fill client mapping.
 */
readonly class ImportedSite
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $externalId,
        public string $name,
        public string $url,
        public string $cmsType = 'wordpress',
        public ?string $suggestedClient = null,
        public array $meta = [],
    ) {}

    public function host(): string
    {
        return (string) (parse_url($this->url, PHP_URL_HOST) ?: $this->url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'name' => $this->name,
            'url' => $this->url,
            'cms_type' => $this->cmsType,
            'suggested_client' => $this->suggestedClient,
            'meta' => $this->meta,
        ];
    }
}
