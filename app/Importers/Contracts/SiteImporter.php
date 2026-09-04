<?php

declare(strict_types=1);

namespace App\Importers\Contracts;

use App\Importers\ImportedSite;
use App\Importers\ImporterException;

/**
 * A connector that lists the WordPress sites an agency already manages on an
 * external platform (MainWP, ManageWP, WPMgr), so they can be imported as
 * Client Reporter sites. Importers are read-only: they fetch and normalise.
 */
interface SiteImporter
{
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /**
     * The CMS the imported sites run, matching Site::cms_type (e.g. 'wordpress').
     * Used to group importers under the CMS chosen on the import screen.
     */
    public function cmsType(): string;

    /**
     * The credential/config fields the operator must supply.
     *
     * @return array<int, array{name: string, label: string, type?: string, placeholder?: string, required?: bool, help?: string}>
     */
    public function configFields(): array;

    /**
     * Fetch and normalise the managed sites.
     *
     * @param  array<string, string>  $config
     * @return array<int, ImportedSite>
     *
     * @throws ImporterException
     */
    public function fetchSites(array $config): array;
}
