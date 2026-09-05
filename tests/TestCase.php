<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Http\DnsResolver;
use App\Support\Http\StaticDnsResolver;
use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never touch real DNS in tests: every host resolves to a public address
        // so faked HTTP calls pass the outbound guard. Tests that exercise the
        // guard itself bind their own StaticDnsResolver map.
        $this->app->instance(DnsResolver::class, new StaticDnsResolver);

        // Treat the app as installed so the EnsureInstalled middleware doesn't
        // redirect feature tests to the wizard. Installer tests clear this.
        if (Schema::hasTable('settings')) {
            app(Settings::class)->set('installed', true);
        }
    }
}
