<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Http;

use App\Support\Http\IpRange;
use App\Support\Http\OutboundUrl;
use App\Support\Http\StaticDnsResolver;
use App\Support\Http\UnsafeUrlException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OutboundUrlTest extends TestCase
{
    private function guard(array $dns = []): OutboundUrl
    {
        return new OutboundUrl(new StaticDnsResolver($dns));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedUrls(): array
    {
        return [
            'ftp scheme' => ['ftp://example.com/file'],
            'file scheme' => ['file:///etc/passwd'],
            'no host' => ['https://'],
            'userinfo' => ['https://user:pw@example.com/'],
            'loopback v4' => ['http://127.0.0.1/'],
            'loopback v4 range' => ['http://127.8.9.10/'],
            'this network' => ['http://0.0.0.0/'],
            'private 10/8' => ['http://10.1.2.3/'],
            'private 172.16/12' => ['http://172.20.0.5/'],
            'private 192.168/16' => ['http://192.168.1.1/'],
            'link-local / cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'carrier-grade nat' => ['http://100.64.0.1/'],
            'multicast' => ['http://224.0.0.1/'],
            'reserved' => ['http://240.0.0.1/'],
            'loopback v6' => ['http://[::1]/'],
            'link-local v6' => ['http://[fe80::1]/'],
            'unique local v6' => ['http://[fd12::1]/'],
            'v4-mapped loopback' => ['http://[::ffff:127.0.0.1]/'],
            'nat64 private' => ['http://[64:ff9b::a00:1]/'],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function test_it_rejects_non_public_destinations(string $url): void
    {
        $this->expectException(UnsafeUrlException::class);

        $this->guard()->assertPublic($url);
    }

    public function test_it_accepts_public_addresses_and_hostnames(): void
    {
        $guard = $this->guard(['analytics.example.com' => ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946']]);

        $this->assertSame('https://93.184.216.34/', $guard->assertPublic('https://93.184.216.34/'));
        $this->assertSame('https://analytics.example.com/matomo', $guard->assertPublic(' https://analytics.example.com/matomo '));
    }

    public function test_a_hostname_resolving_to_a_private_address_is_rejected(): void
    {
        $guard = $this->guard(['evil.test' => ['10.0.0.1']]);

        $this->expectException(UnsafeUrlException::class);
        $guard->assertPublic('https://evil.test/');
    }

    public function test_mixed_public_and_private_records_are_rejected(): void
    {
        $guard = $this->guard(['mixed.test' => ['93.184.216.34', '192.168.0.9']]);

        $this->expectException(UnsafeUrlException::class);
        $guard->assertPublic('https://mixed.test/');
    }

    public function test_an_unresolvable_hostname_is_rejected(): void
    {
        $guard = $this->guard(['gone.test' => []]);

        $this->expectException(UnsafeUrlException::class);
        $guard->assertPublic('https://gone.test/');
    }

    public function test_private_addresses_can_be_allowed_globally(): void
    {
        config(['client-reporter.outbound.allow_private' => true]);

        $this->assertSame('http://192.168.1.10/', $this->guard()->assertPublic('http://192.168.1.10/'));
    }

    public function test_specific_hosts_can_be_allow_listed(): void
    {
        config(['client-reporter.outbound.allowed_hosts' => ['matomo.internal']]);
        $guard = $this->guard(['matomo.internal' => ['10.0.0.5']]);

        $this->assertSame('http://matomo.internal/', $guard->assertPublic('http://matomo.internal/'));

        $this->expectException(UnsafeUrlException::class);
        $guard->assertPublic('http://10.0.0.5/');
    }

    public function test_redirect_hops_are_revalidated(): void
    {
        $options = $this->guard()->redirectOptions()['allow_redirects'];
        $this->assertSame(3, $options['max']);

        $onRedirect = $options['on_redirect'];
        $onRedirect(new Request('GET', 'https://example.com'), new Response(302), new Uri('https://93.184.216.34/next'));

        $this->expectException(UnsafeUrlException::class);
        $onRedirect(new Request('GET', 'https://example.com'), new Response(302), new Uri('http://169.254.169.254/'));
    }

    public function test_ip_range_classification(): void
    {
        $this->assertTrue(IpRange::isPublic('8.8.8.8'));
        $this->assertTrue(IpRange::isPublic('2606:4700::1111'));
        $this->assertFalse(IpRange::isPublic('not-an-ip'));
        $this->assertFalse(IpRange::isPublic('10.255.255.255'));
        $this->assertFalse(IpRange::isPublic('172.31.255.254'));
        $this->assertTrue(IpRange::isPublic('172.32.0.1'));
        $this->assertTrue(IpRange::contains('192.168.0.0/16', '192.168.200.1'));
        $this->assertFalse(IpRange::contains('192.168.0.0/16', '192.169.0.1'));
    }
}
