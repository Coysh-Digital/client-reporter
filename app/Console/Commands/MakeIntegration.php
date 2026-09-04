<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Scaffolds a new integration as a self-contained Composer package skeleton,
 * ready to fill in and publish. The generated package declares its Integration
 * class under extra.client-reporter.integrations, so installing it is all that
 * is needed for Client Reporter to discover it.
 */
class MakeIntegration extends Command
{
    protected $signature = 'client-reporter:make-integration {name : The integration name, e.g. "Matomo"}';

    protected $description = 'Scaffold a new Client Reporter integration package';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $studly = Str::studly($name);
        $slug = Str::slug($name);
        $namespace = "ClientReporter\\{$studly}";
        $dir = base_path("extensions/{$slug}");

        if (is_dir($dir)) {
            $this->error("A package already exists at extensions/{$slug}.");

            return self::FAILURE;
        }

        @mkdir($dir.'/src', 0755, true);

        $this->write("{$dir}/composer.json", $this->composerJson($slug, $namespace, $studly));
        $this->write("{$dir}/src/{$studly}Integration.php", $this->integrationStub($namespace, $studly, $slug, $name));
        $this->write("{$dir}/src/{$studly}Collector.php", $this->collectorStub($namespace, $studly));
        $this->write("{$dir}/README.md", $this->readme($name, $slug));

        $this->components->info("Integration scaffolded at extensions/{$slug}");
        $this->line('  1. Fill in the manifest, config fields, verify() and collector.');
        $this->line('  2. Add the package repository and require it, or move it to its own repo.');
        $this->line("  3. Run: composer require clientreporter/{$slug}");

        return self::SUCCESS;
    }

    private function write(string $path, string $contents): void
    {
        file_put_contents($path, $contents);
    }

    private function composerJson(string $slug, string $namespace, string $studly): string
    {
        $escapedNamespace = str_replace('\\', '\\\\', $namespace).'\\\\';

        return <<<JSON
        {
            "name": "clientreporter/{$slug}",
            "description": "A Client Reporter integration.",
            "type": "library",
            "license": "MIT",
            "require": {
                "php": "^8.3"
            },
            "autoload": {
                "psr-4": {
                    "{$escapedNamespace}": "src/"
                }
            },
            "extra": {
                "client-reporter": {
                    "integrations": [
                        "{$escapedNamespace}{$studly}Integration"
                    ]
                }
            }
        }

        JSON;
    }

    private function integrationStub(string $namespace, string $studly, string $slug, string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use App\\Integrations\\Contracts\\Integration;
        use App\\Integrations\\Support\\AuthMethod;
        use App\\Integrations\\Support\\ConfigField;
        use App\\Integrations\\Support\\IntegrationCategory;
        use App\\Integrations\\Support\\IntegrationException;
        use App\\Integrations\\Support\\IntegrationManifest;
        use App\\Integrations\\Support\\VerificationResult;
        use App\\Models\\SiteIntegration;

        class {$studly}Integration extends Integration
        {
            public function manifest(): IntegrationManifest
            {
                return new IntegrationManifest(
                    key: '{$slug}',
                    name: '{$name}',
                    category: IntegrationCategory::Analytics,
                    authMethod: AuthMethod::ApiKey,
                    description: 'Describe what {$name} reports.',
                );
            }

            /**
             * @return array<int, ConfigField>
             */
            public function configFields(): array
            {
                return [
                    ConfigField::apiKey(),
                ];
            }

            public function verify(SiteIntegration \$connection): VerificationResult
            {
                try {
                    // Make a lightweight authenticated call to confirm the credentials.
                    // throw new IntegrationException('...') on failure.
                } catch (IntegrationException \$e) {
                    return VerificationResult::failure(\$e->getMessage());
                }

                return VerificationResult::success('Connected.');
            }

            /**
             * @return array<int, \\App\\Integrations\\Contracts\\Collector>
             */
            public function collectors(): array
            {
                return [new {$studly}Collector];
            }
        }

        PHP;
    }

    private function collectorStub(string $namespace, string $studly): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use App\\Integrations\\Contracts\\AbstractCollector;
        use App\\Integrations\\Support\\CollectorResult;
        use App\\Models\\SiteIntegration;
        use App\\Support\\DateRange;

        class {$studly}Collector extends AbstractCollector
        {
            public function key(): string
            {
                return 'summary';
            }

            public function collect(SiteIntegration \$connection, DateRange \$range): CollectorResult
            {
                // Fetch data from the external service for \$range, then:
                return CollectorResult::make()
                    ->metric('example.value', 0)
                    ->snapshot(['items' => []]);
            }
        }

        PHP;
    }

    private function readme(string $name, string $slug): string
    {
        return <<<MD
        # {$name} integration for Client Reporter

        A Client Reporter integration package. Once installed via Composer, Client Reporter
        discovers it automatically through `extra.client-reporter.integrations`.

        ## Develop

        - Fill in `src/{$name}Integration.php` (manifest, config fields, `verify()`).
        - Implement data collection in `src/{$name}Collector.php`.
        - Verify compatibility with the `IntegrationContractAssertions` test helper.

        ## Install

        ```
        composer require clientreporter/{$slug}
        ```
        MD;
    }
}
