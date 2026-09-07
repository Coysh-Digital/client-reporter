<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Integrations\EmailOctopus\EmailOctopusIntegration;
use App\Integrations\EmailOctopus\SummaryCollector;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailOctopusTest extends TestCase
{
    use RefreshDatabase;

    private function connection(): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'credentials' => ['api_key' => 'eo-valid-key'],
            'settings' => ['list_id' => 'list-123'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $newContacts
     */
    private function fakeList(int $subscribed = 500, array $newContacts = []): void
    {
        Http::fake([
            'api.emailoctopus.com/lists/list-123/contacts*' => Http::response([
                'data' => $newContacts,
                'paging' => ['next' => null],
            ]),
            'api.emailoctopus.com/lists/list-123' => Http::response([
                'name' => 'Newsletter',
                'counts' => ['subscribed' => $subscribed, 'unsubscribed' => 12, 'pending' => 3],
            ]),
        ]);
    }

    public function test_verify_succeeds_with_a_valid_key_and_list(): void
    {
        $this->fakeList();

        $result = (new EmailOctopusIntegration)->verify($this->connection());

        $this->assertTrue($result->ok);
        $this->assertStringContainsString('Newsletter', $result->message);
        $this->assertStringContainsString('500', $result->message);
    }

    public function test_verify_fails_gracefully_when_the_key_is_rejected(): void
    {
        Http::fake(['api.emailoctopus.com/*' => Http::response('', 401)]);

        $result = (new EmailOctopusIntegration)->verify($this->connection());

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('rejected', $result->message);
    }

    public function test_verify_fails_when_the_list_is_missing(): void
    {
        Http::fake(['api.emailoctopus.com/*' => Http::response('', 404)]);

        $result = (new EmailOctopusIntegration)->verify($this->connection());

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('List ID', $result->message);
    }

    public function test_collector_reports_new_subscribers_and_total_audience(): void
    {
        $this->fakeList(subscribed: 500, newContacts: [
            ['id' => 'c1'], ['id' => 'c2'], ['id' => 'c3'],
        ]);

        $result = (new SummaryCollector)->collect($this->connection(), new DateRange('2026-08-01', '2026-08-31'));

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertSame(3, (int) $metrics['leads.new']->value);
        $this->assertSame(500, (int) $metrics['leads.total']->value);
    }

    public function test_collector_pages_through_all_new_contacts(): void
    {
        Http::fake([
            'api.emailoctopus.com/lists/list-123/contacts*' => Http::sequence()
                ->push(['data' => [['id' => 'a'], ['id' => 'b']], 'paging' => ['next' => ['starting_after' => 'cursor-1']]])
                ->push(['data' => [['id' => 'c']], 'paging' => ['next' => null]]),
            'api.emailoctopus.com/lists/list-123' => Http::response([
                'name' => 'Newsletter',
                'counts' => ['subscribed' => 800],
            ]),
        ]);

        $result = (new SummaryCollector)->collect($this->connection(), new DateRange('2026-08-01', '2026-08-31'));

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertSame(3, (int) $metrics['leads.new']->value);
        $this->assertSame(800, (int) $metrics['leads.total']->value);
    }

    public function test_counts_tolerate_the_array_shape(): void
    {
        // The API has been seen to wrap `counts` in a single-element array.
        Http::fake([
            'api.emailoctopus.com/lists/list-123/contacts*' => Http::response(['data' => [], 'paging' => ['next' => null]]),
            'api.emailoctopus.com/lists/list-123' => Http::response([
                'name' => 'Newsletter',
                'counts' => [['subscribed' => 640, 'unsubscribed' => 0, 'pending' => 0]],
            ]),
        ]);

        $result = (new SummaryCollector)->collect($this->connection(), new DateRange('2026-08-01', '2026-08-31'));

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertSame(640, (int) $metrics['leads.total']->value);
    }
}
