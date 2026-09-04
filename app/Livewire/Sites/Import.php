<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Importers\Contracts\SiteImporter;
use App\Importers\ImporterException;
use App\Importers\SiteImporterRegistry;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Models\Client;
use App\Models\Site;
use App\Support\AuditLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Import sites')]
class Import extends Component
{
    /** Selected CMS key (matches Site::cms_type / a CMS integration key). */
    public string $cms = '';

    /** Selected import source key; scoped to importers for the chosen CMS. */
    public string $provider = '';

    /** @var array<string, string> */
    public array $config = [];

    public ?string $error = null;

    public bool $fetched = false;

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array{created: int, skipped: int}|null */
    public ?array $result = null;

    public function mount(): void
    {
        $this->authorize('manage-sites');

        $options = $this->cmsOptions();
        // Prefer the first CMS that actually has an import source.
        $withSources = array_values(array_filter($options, fn (array $c): bool => $this->importersForCms($c['key']) !== []));
        $this->cms = $withSources[0]['key'] ?? ($options[0]['key'] ?? '');
        $this->syncProvider();
    }

    public function updatedCms(): void
    {
        $this->syncProvider();
        $this->resetImportState();
    }

    public function updatedProvider(): void
    {
        $this->resetConfig();
        $this->resetImportState();
    }

    public function fetch(): void
    {
        $this->authorize('manage-sites');
        $this->error = null;
        $this->result = null;

        $importer = $this->importer();
        if ($importer === null) {
            return;
        }

        foreach ($importer->configFields() as $field) {
            if (($field['required'] ?? false) && trim($this->config[$field['name']] ?? '') === '') {
                $this->error = $field['label'].' is required.';

                return;
            }
        }

        try {
            $sites = $importer->fetchSites($this->config);
        } catch (ImporterException $e) {
            $this->error = $e->getMessage();
            $this->rows = [];
            $this->fetched = false;

            return;
        }

        $existingUrls = $this->existingUrlSet();
        $clientsByName = [];
        foreach (Client::query()->get(['id', 'name']) as $client) {
            $clientsByName[mb_strtolower($client->name)] = $client->id;
        }

        $this->rows = [];
        foreach ($sites as $site) {
            $already = in_array($this->normaliseUrl($site->url), $existingUrls, true);
            $suggested = $site->suggestedClient ?: $site->name;
            $matchId = $clientsByName[mb_strtolower($suggested)] ?? null;

            $this->rows[] = [
                'external_id' => $site->externalId,
                'name' => $site->name,
                'url' => $site->url,
                'host' => $site->host(),
                'cms_type' => $site->cmsType,
                'already' => $already,
                'include' => ! $already,
                'client_choice' => $matchId !== null ? (string) $matchId : 'new',
                'new_client_name' => $suggested,
            ];
        }

        $this->fetched = true;

        if ($this->rows === []) {
            $this->error = 'No sites were found for these credentials.';
        }
    }

    public function import(AuditLogger $audit): void
    {
        $this->authorize('manage-sites');

        $existingUrls = $this->existingUrlSet();
        $created = 0;
        $skipped = 0;

        foreach ($this->rows as $row) {
            if (! ($row['include'] ?? false)) {
                continue;
            }

            $normalised = $this->normaliseUrl((string) $row['url']);
            if (in_array($normalised, $existingUrls, true)) {
                $skipped++;

                continue;
            }

            $client = $this->resolveClient($row);

            Site::create([
                'client_id' => $client->id,
                'name' => (string) $row['name'],
                'url' => (string) $row['url'],
                'cms_type' => $row['cms_type'] ?: ($this->cms ?: 'wordpress'),
                'is_active' => true,
            ]);

            $existingUrls[] = $normalised;
            $created++;
        }

        $audit->log('sites.imported', metadata: ['cms' => $this->cms, 'provider' => $this->provider, 'created' => $created, 'skipped' => $skipped]);

        $this->result = ['created' => $created, 'skipped' => $skipped];
        $this->rows = [];
        $this->fetched = false;
    }

    private function syncProvider(): void
    {
        $importers = $this->importersForCms($this->cms);
        $this->provider = $importers === [] ? '' : $importers[0]->key();
        $this->resetConfig();
    }

    private function resetImportState(): void
    {
        $this->rows = [];
        $this->fetched = false;
        $this->error = null;
        $this->result = null;
    }

    private function importer(): ?SiteImporter
    {
        return $this->provider === '' ? null : app(SiteImporterRegistry::class)->find($this->provider);
    }

    private function resetConfig(): void
    {
        $this->config = [];
        $importer = $this->importer();
        if ($importer !== null) {
            foreach ($importer->configFields() as $field) {
                $this->config[$field['name']] = '';
            }
        }
    }

    /**
     * The CMS platforms enabled via installed CMS integrations.
     *
     * @return array<int, array{key: string, name: string}>
     */
    private function cmsOptions(): array
    {
        $options = [];
        foreach (app(IntegrationRegistry::class)->all() as $integration) {
            $manifest = $integration->manifest();
            if ($manifest->category === IntegrationCategory::Cms) {
                $options[] = ['key' => $manifest->key, 'name' => $manifest->name];
            }
        }

        usort($options, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $options;
    }

    /**
     * @return array<int, SiteImporter>
     */
    private function importersForCms(string $cms): array
    {
        return array_values(array_filter(
            app(SiteImporterRegistry::class)->all(),
            fn (SiteImporter $importer): bool => $importer->cmsType() === $cms,
        ));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveClient(array $row): Client
    {
        $choice = (string) ($row['client_choice'] ?? 'new');

        if ($choice !== 'new') {
            $client = Client::find((int) $choice);
            if ($client !== null) {
                return $client;
            }
        }

        $name = trim((string) ($row['new_client_name'] ?? '')) ?: (string) $row['name'];

        return Client::firstOrCreate(['name' => $name], ['is_active' => true]);
    }

    /**
     * @return array<int, string>
     */
    private function existingUrlSet(): array
    {
        return Site::query()->pluck('url')
            ->map(fn ($url): string => $this->normaliseUrl((string) $url))
            ->all();
    }

    private function normaliseUrl(string $url): string
    {
        return rtrim(mb_strtolower(trim($url)), '/');
    }

    public function render(): mixed
    {
        $options = $this->cmsOptions();
        $importer = $this->importer();
        $currentCms = collect($options)->firstWhere('key', $this->cms);

        return view('livewire.sites.import', [
            'cmsOptions' => $options,
            'currentCmsName' => $currentCms['name'] ?? $this->cms,
            'importers' => $this->importersForCms($this->cms),
            'fields' => $importer?->configFields() ?? [],
            'clients' => Client::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
