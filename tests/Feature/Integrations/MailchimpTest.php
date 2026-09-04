<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Integrations\Mailchimp\MailchimpIntegration;
use App\Integrations\Mailchimp\SummaryCollector;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MailchimpTest extends TestCase
{
    use RefreshDatabase;

    private function connection(): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'credentials' => ['api_key' => 'valid-us21'],
            'settings' => ['list_id' => 'abc123'],
        ]);
    }

    private function fakeList(array $stats = ['member_count' => 500]): void
    {
        Http::fake([
            'us21.api.mailchimp.com/3.0/lists/abc123' => Http::response(['name' => 'Newsletter', 'stats' => $stats]),
            'us21.api.mailchimp.com/3.0/lists/abc123/growth-history*' => Http::response(['history' => [
                ['month' => '2026-08-01', 'existing' => 480, 'imports' => 5, 'optins' => 15],
                ['month' => '2026-07-01', 'existing' => 460, 'imports' => 0, 'optins' => 20],
            ]]),
        ]);
    }

    public function test_verify_succeeds_with_a_valid_key_and_list(): void
    {
        $this->fakeList();

        $result = (new MailchimpIntegration)->verify($this->connection());

        $this->assertTrue($result->ok);
        $this->assertStringContainsString('Newsletter', $result->message);
    }

    public function test_verify_fails_gracefully_when_the_key_is_rejected(): void
    {
        Http::fake(['us21.api.mailchimp.com/*' => Http::response('', 401)]);

        $result = (new MailchimpIntegration)->verify($this->connection());

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('rejected', $result->message);
    }

    public function test_an_invalid_key_without_a_datacenter_suffix_fails_fast(): void
    {
        $connection = SiteIntegration::factory()->create(['credentials' => ['api_key' => 'nohyphens']]);

        $result = (new MailchimpIntegration)->verify($connection);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('datacenter', $result->message);
    }

    public function test_collector_sums_full_months_contained_in_the_period(): void
    {
        $this->fakeList();

        $result = (new SummaryCollector)->collect($this->connection(), new DateRange('2026-08-01', '2026-08-31'));

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertSame(20, (int) $metrics['leads.new']->value); // August: 5 imports + 15 optins
        $this->assertSame(500, (int) $metrics['leads.total']->value);
    }

    public function test_a_partial_month_range_does_not_count_that_month(): void
    {
        $this->fakeList();

        $result = (new SummaryCollector)->collect($this->connection(), new DateRange('2026-08-15', '2026-08-31'));

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertSame(0, (int) $metrics['leads.new']->value);
    }
}
