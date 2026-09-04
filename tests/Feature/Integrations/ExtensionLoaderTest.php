<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Integrations\ExtensionLoader;
use CrExtFixture\Widget;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExtensionLoaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/cr-ext-'.uniqid();
        File::ensureDirectoryExists($this->dir.'/acme/src');

        File::put($this->dir.'/acme/composer.json', json_encode([
            'name' => 'clientreporter/acme',
            'autoload' => ['psr-4' => ['CrExtFixture\\' => 'src/']],
            'extra' => ['client-reporter' => ['integrations' => ['CrExtFixture\\AcmeIntegration']]],
        ]));

        File::put($this->dir.'/acme/src/Widget.php', "<?php\n\nnamespace CrExtFixture;\n\nclass Widget {}\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_it_discovers_integration_classes_declared_by_extensions(): void
    {
        $this->assertContains('CrExtFixture\\AcmeIntegration', ExtensionLoader::integrationClasses($this->dir));
    }

    public function test_it_autoloads_extension_classes_without_composer(): void
    {
        $this->assertFalse(class_exists(Widget::class, false));

        ExtensionLoader::registerAutoloaders($this->dir);

        $this->assertTrue(class_exists(Widget::class));
    }

    public function test_it_reads_the_local_registration_file(): void
    {
        $file = $this->dir.'/client-reporter.local.php';
        File::put($file, "<?php return ['integrations' => ['CrExtFixture\\\\AcmeIntegration']];");

        $this->assertSame(['CrExtFixture\\AcmeIntegration'], ExtensionLoader::localIntegrationClasses($file));
    }

    public function test_a_missing_local_file_returns_no_classes(): void
    {
        $this->assertSame([], ExtensionLoader::localIntegrationClasses($this->dir.'/does-not-exist.php'));
    }

    public function test_an_empty_extensions_dir_yields_nothing(): void
    {
        $empty = $this->dir.'/empty';
        File::ensureDirectoryExists($empty);

        $this->assertSame([], ExtensionLoader::integrationClasses($empty));
    }
}
