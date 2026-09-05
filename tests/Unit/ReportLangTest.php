<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ReportLang;
use Tests\TestCase;

class ReportLangTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['report-language' => [
            'common' => [
                'source' => 'Source',
                'this_period' => 'This period',
            ],
            'traffic' => [
                'heading' => 'Site traffic',
                'summary' => ':count visitors this period, up :percent% on the previous period.',
                'tile' => ['visitors' => 'Visitors', 'visits' => 'Visits'],
            ],
            'uptime' => [
                'legend' => ['healthy' => 'Healthy', 'partial' => 'Partial'],
            ],
        ]]);

        ReportLang::setLocalPath(null);
        ReportLang::flush();
    }

    protected function tearDown(): void
    {
        ReportLang::setLocalPath(null);
        ReportLang::flush();

        parent::tearDown();
    }

    public function test_it_resolves_a_dotted_key(): void
    {
        $this->assertSame('Site traffic', ReportLang::get('traffic.heading'));
        $this->assertSame('Healthy', ReportLang::get('uptime.legend.healthy'));
    }

    public function test_it_substitutes_named_placeholders(): void
    {
        $this->assertSame(
            '12,480 visitors this period, up 18% on the previous period.',
            ReportLang::get('traffic.summary', ['count' => '12,480', 'percent' => 18]),
        );
    }

    public function test_an_unknown_key_falls_back_to_the_default_then_the_key(): void
    {
        $this->assertSame('Fallback', ReportLang::get('traffic.missing', [], 'Fallback'));
        $this->assertSame('traffic.missing', ReportLang::get('traffic.missing'));
    }

    public function test_a_local_override_replaces_only_the_keys_it_sets(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rl').'.php';
        file_put_contents($path, "<?php return ['traffic' => ['heading' => 'Website visitors', 'tile' => ['visitors' => 'Unique visitors']]];");

        ReportLang::setLocalPath($path);

        // Overridden leaves win...
        $this->assertSame('Website visitors', ReportLang::get('traffic.heading'));
        $this->assertSame('Unique visitors', ReportLang::get('traffic.tile.visitors'));
        // ...while sibling and untouched defaults survive (never goes stale).
        $this->assertSame('Visits', ReportLang::get('traffic.tile.visits'));
        $this->assertSame('Source', ReportLang::get('common.source'));

        unlink($path);
    }

    public function test_a_malformed_override_file_is_ignored(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rl').'.php';
        file_put_contents($path, "<?php return 'not an array';");

        ReportLang::setLocalPath($path);

        $this->assertSame('Site traffic', ReportLang::get('traffic.heading'));

        unlink($path);
    }
}
