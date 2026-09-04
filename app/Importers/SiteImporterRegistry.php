<?php

declare(strict_types=1);

namespace App\Importers;

use App\Importers\Contracts\SiteImporter;

/**
 * Holds the available site importers, keyed by their platform key.
 */
class SiteImporterRegistry
{
    /** @var array<string, SiteImporter> */
    private array $importers = [];

    /**
     * @param  array<int, SiteImporter>  $importers
     */
    public function __construct(array $importers = [])
    {
        foreach ($importers as $importer) {
            $this->importers[$importer->key()] = $importer;
        }
    }

    /**
     * @return array<int, SiteImporter>
     */
    public function all(): array
    {
        return array_values($this->importers);
    }

    public function find(string $key): ?SiteImporter
    {
        return $this->importers[$key] ?? null;
    }
}
