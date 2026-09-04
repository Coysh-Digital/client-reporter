<?php

declare(strict_types=1);

namespace Tests\Feature\Sites;

use App\Importers\ImporterException;
use App\Importers\MainWpImporter;
use App\Importers\ManageWpImporter;
use App\Importers\WpMgrImporter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportersTest extends TestCase
{
    public function test_wpmgr_importer_normalises_the_site_list(): void
    {
        Http::fake(['manage.wpmgr.app/*' => Http::response([
            'items' => [
                ['id' => 's1', 'url' => 'https://alpha.test', 'name' => 'Alpha', 'client_name' => 'Alpha Ltd', 'wp_version' => '6.6'],
                ['id' => 's2', 'url' => 'https://beta.test', 'name' => 'Beta'],
                ['id' => 's3', 'name' => 'No URL — skipped'],
            ],
        ])]);

        $sites = (new WpMgrImporter)->fetchSites(['api_key' => 'wpmgr_test']);

        $this->assertCount(2, $sites);
        $this->assertSame('Alpha', $sites[0]->name);
        $this->assertSame('https://alpha.test', $sites[0]->url);
        $this->assertSame('Alpha Ltd', $sites[0]->suggestedClient);
        $this->assertNull($sites[1]->suggestedClient);
    }

    public function test_wpmgr_importer_excludes_disabled_sites_and_sends_no_broken_filters(): void
    {
        // Confirmed against a live WPMgr instance: sending a "state" or
        // "include_archived" query param at all silently zeroes the result
        // set. The endpoint must be called with no query params, filtering
        // client-side on the real "status" field instead.
        Http::fake(['manage.wpmgr.app/*' => Http::response([
            'items' => [
                ['id' => 's1', 'url' => 'https://alpha.test', 'name' => 'Alpha', 'status' => 'active'],
                ['id' => 's2', 'url' => 'https://beta.test', 'name' => 'Beta', 'status' => 'disabled'],
            ],
        ])]);

        $sites = (new WpMgrImporter)->fetchSites(['api_key' => 'wpmgr_test']);

        $this->assertCount(1, $sites);
        $this->assertSame('Alpha', $sites[0]->name);

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'state=') && ! str_contains($request->url(), 'include_archived'));
    }

    public function test_wpmgr_importer_reports_a_rejected_key(): void
    {
        Http::fake(['manage.wpmgr.app/*' => Http::response([], 401)]);

        $this->expectException(ImporterException::class);
        (new WpMgrImporter)->fetchSites(['api_key' => 'bad']);
    }

    public function test_wpmgr_importer_requires_an_api_key(): void
    {
        $this->expectException(ImporterException::class);
        (new WpMgrImporter)->fetchSites(['api_key' => '']);
    }

    public function test_mainwp_importer_parses_all_sites(): void
    {
        Http::fake(['dash.example.com/*' => Http::response([
            ['id' => 1, 'url' => 'https://one.test', 'name' => 'One', 'wpversion' => '6.5'],
            ['id' => 2, 'url' => 'https://two.test', 'name' => 'Two'],
        ])]);

        $sites = (new MainWpImporter)->fetchSites([
            'dashboard_url' => 'https://dash.example.com',
            'consumer_key' => 'ck_x',
            'consumer_secret' => 'cs_y',
        ]);

        $this->assertCount(2, $sites);
        $this->assertSame('https://one.test', $sites[0]->url);
    }

    public function test_managewp_importer_parses_websites_envelope(): void
    {
        Http::fake(['api.managewp.com/*' => Http::response([
            'websites' => [
                ['id' => 10, 'url' => 'https://x.test', 'name' => 'X'],
                ['id' => 11, 'site_url' => 'https://y.test', 'title' => 'Y'],
            ],
        ])]);

        $sites = (new ManageWpImporter)->fetchSites(['api_key' => 'key']);

        $this->assertCount(2, $sites);
        $this->assertSame('https://x.test', $sites[0]->url);
        $this->assertSame('Y', $sites[1]->name);
    }
}
