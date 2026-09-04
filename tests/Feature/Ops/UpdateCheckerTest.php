<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Support\UpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_a_newer_release(): void
    {
        config(['client-reporter.version' => '0.1.0']);
        Http::fake(['api.github.com/*' => Http::response([
            'tag_name' => 'v0.2.0', 'html_url' => 'https://github.com/coysh-digital/client-reporter/releases/tag/v0.2.0',
        ])]);

        $status = app(UpdateChecker::class)->check();

        $this->assertTrue($status['update_available']);
        $this->assertSame('0.2.0', $status['latest']);
    }

    public function test_it_reports_up_to_date(): void
    {
        config(['client-reporter.version' => '0.2.0']);
        Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v0.2.0', 'html_url' => 'https://example.com'])]);

        $this->assertFalse(app(UpdateChecker::class)->check()['update_available']);
    }

    public function test_a_failed_check_does_not_throw(): void
    {
        Http::fake(['api.github.com/*' => Http::response('nope', 500)]);

        $status = app(UpdateChecker::class)->check();

        $this->assertFalse($status['update_available']);
    }
}
