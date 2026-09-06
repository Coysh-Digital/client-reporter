<?php

declare(strict_types=1);

namespace Tests\Unit\Integrations\Support;

use App\Integrations\Support\AbstractHttpClient;
use App\Integrations\Support\AuthenticationException;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\RateLimitedException;
use App\Support\Http\DnsResolver;
use App\Support\Http\StaticDnsResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbstractHttpClientTest extends TestCase
{
    private function vendorClient(): VendorClientDouble
    {
        return new VendorClientDouble;
    }

    private function selfHostedClient(string $base): SelfHostedClientDouble
    {
        return new SelfHostedClientDouble($base);
    }

    public function test_a_server_error_is_retried_and_then_succeeds(): void
    {
        Http::fake([
            'api.acme.test/*' => Http::sequence()->push('boom', 503)->push(['ok' => true]),
        ]);

        $this->assertSame(['ok' => true], $this->vendorClient()->ping());
        Http::assertSentCount(2);
    }

    public function test_requests_identify_the_application(): void
    {
        Http::fake(['api.acme.test/*' => Http::response(['ok' => true])]);

        $this->vendorClient()->ping();

        Http::assertSent(fn (Request $request): bool => str_starts_with((string) $request->header('User-Agent')[0], 'ClientReporter/'));
    }

    public function test_a_connection_error_becomes_a_safe_message(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: unreachable https://api.acme.test/ping?key=secret'));

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('Could not reach Acme. Please try again shortly.');

        $this->vendorClient()->ping();
    }

    public function test_a_401_becomes_an_authentication_exception(): void
    {
        Http::fake(['api.acme.test/*' => Http::response('nope', 401)]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Acme rejected the credentials');

        $this->vendorClient()->ping();
    }

    public function test_a_429_becomes_a_rate_limited_exception(): void
    {
        Http::fake(['api.acme.test/*' => Http::response('slow', 429)]);

        $this->expectException(RateLimitedException::class);

        $this->vendorClient()->ping();
    }

    public function test_a_self_hosted_base_url_on_a_private_address_is_refused(): void
    {
        Http::fake();
        $this->app->instance(DnsResolver::class, new StaticDnsResolver(['intranet.test' => ['192.168.1.5']]));

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('private or local network');

        $this->selfHostedClient('https://intranet.test')->status();

        Http::assertNothingSent();
    }

    public function test_a_public_base_url_is_joined_with_the_path(): void
    {
        Http::fake(['analytics.example.com/api/status' => Http::response('', 200)]);

        $this->assertSame(200, $this->selfHostedClient('https://analytics.example.com/')->status());
    }
}

final class VendorClientDouble extends AbstractHttpClient
{
    protected int $retryDelayMs = 0;

    protected function provider(): string
    {
        return 'Acme';
    }

    /** @return array<mixed> */
    public function ping(): array
    {
        return $this->json($this->guard($this->get('https://api.acme.test/ping')));
    }
}

final class SelfHostedClientDouble extends AbstractHttpClient
{
    protected int $retries = 0;

    public function __construct(private readonly string $base) {}

    protected function provider(): string
    {
        return 'Matomo';
    }

    protected function baseUrl(): string
    {
        return $this->base;
    }

    public function status(): int
    {
        return $this->guard($this->get('/api/status'))->status();
    }
}
