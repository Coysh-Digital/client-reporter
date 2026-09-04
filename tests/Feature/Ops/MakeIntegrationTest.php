<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MakeIntegrationTest extends TestCase
{
    public function test_it_scaffolds_an_integration_package(): void
    {
        $dir = base_path('extensions/matomo');
        File::deleteDirectory($dir);

        $this->artisan('client-reporter:make-integration', ['name' => 'Matomo'])->assertSuccessful();

        $this->assertFileExists($dir.'/composer.json');
        $this->assertFileExists($dir.'/src/MatomoIntegration.php');
        $this->assertFileExists($dir.'/src/MatomoCollector.php');

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true);
        $this->assertSame(
            ['ClientReporter\\Matomo\\MatomoIntegration'],
            $composer['extra']['client-reporter']['integrations'],
        );

        File::deleteDirectory($dir);
    }
}
