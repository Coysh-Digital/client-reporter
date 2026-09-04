<?php

declare(strict_types=1);

namespace App\Integrations\Testing;

use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\ConfigField;
use App\Reporting\Contracts\BlockType;
use PHPUnit\Framework\Assert;

/**
 * Reusable assertions that verify an Integration honours Client Reporter's
 * contract. Third-party integration packages can `use` this trait in their own
 * tests to confirm compatibility, and Client Reporter uses it against every
 * bundled integration.
 */
trait IntegrationContractAssertions
{
    public function assertValidIntegration(Integration $integration): void
    {
        $manifest = $integration->manifest();

        Assert::assertMatchesRegularExpression('/^[a-z0-9_]+$/', $manifest->key, 'Integration key must be lower snake/slug case.');
        Assert::assertNotSame('', trim($manifest->name), 'Integration must have a name.');
        Assert::assertNotSame('', trim($manifest->description), 'Integration should describe itself.');
        Assert::assertSame($manifest->key, $integration->key(), 'key() must match the manifest key.');

        foreach ($integration->configFields() as $field) {
            Assert::assertInstanceOf(ConfigField::class, $field, 'configFields() must return ConfigField instances.');
            Assert::assertNotSame('', trim($field->key), 'Every config field needs a key.');
        }

        foreach ($integration->collectors() as $collector) {
            Assert::assertInstanceOf(Collector::class, $collector, 'collectors() must return Collector instances.');
            Assert::assertNotSame('', trim($collector->key()), 'Every collector needs a key.');
            Assert::assertGreaterThan(0, $collector->intervalMinutes(), 'Collector interval must be positive.');
        }

        foreach ($integration->reportBlocks() as $blockClass) {
            Assert::assertTrue(
                is_subclass_of($blockClass, BlockType::class),
                "Report block [{$blockClass}] must extend BlockType.",
            );
        }
    }
}
