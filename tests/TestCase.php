<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Treat the app as installed so the EnsureInstalled middleware doesn't
        // redirect feature tests to the wizard. Installer tests clear this.
        if (Schema::hasTable('settings')) {
            app(Settings::class)->set('installed', true);
        }
    }
}
