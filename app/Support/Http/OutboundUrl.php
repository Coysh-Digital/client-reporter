<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Guards every outbound request made to a URL that staff typed in — site
 * addresses, self-hosted analytics instances, companion-plugin endpoints,
 * import sources. Only http(s) is allowed, credentials in the URL are refused,
 * and the host must resolve exclusively to publicly routable addresses so the
 * server cannot be pointed at localhost, the LAN or a cloud metadata service.
 * Redirects are re-checked hop by hop.
 *
 * Self-hosted installs that legitimately talk to a private address (Matomo on
 * the same LAN, say) can opt in via `client-reporter.outbound.allow_private`
 * or list specific hosts in `client-reporter.outbound.allowed_hosts`.
 *
 * Known limit: the address is checked when the URL is validated and again
 * when the request is built, but a DNS answer that changes between resolution
 * and connection (rebinding) is not pinned.
 */
final class OutboundUrl
{
    private const MAX_REDIRECTS = 3;

    public function __construct(private readonly DnsResolver $dns) {}

    /**
     * Validate a URL and return it normalised (trimmed, no trailing whitespace).
     *
     * @throws UnsafeUrlException
     */
    public function assertPublic(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            throw new UnsafeUrlException('That is not a valid web address.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UnsafeUrlException('Only http:// and https:// addresses are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrlException('Addresses must not contain a username or password.');
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if ($this->isAllowListed($host)) {
            return $url;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : $this->dns->resolve($host);

        if ($addresses === []) {
            throw new UnsafeUrlException("The address {$host} could not be resolved.");
        }

        foreach ($addresses as $address) {
            if (! IpRange::isPublic($address)) {
                throw new UnsafeUrlException("The address {$host} points to a private or local network, which is not allowed.");
            }
        }

        return $url;
    }

    /**
     * @throws UnsafeUrlException
     */
    public static function check(string $url): string
    {
        return app(self::class)->assertPublic($url);
    }

    /**
     * Guzzle options that follow at most a few redirects and re-validate the
     * destination of each one before it is fetched.
     *
     * @return array<string, mixed>
     */
    public function redirectOptions(): array
    {
        return [
            'allow_redirects' => [
                'max' => self::MAX_REDIRECTS,
                'strict' => true,
                'referer' => false,
                'protocols' => ['http', 'https'],
                'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $next): void {
                    $this->assertPublic((string) $next);
                },
            ],
        ];
    }

    /**
     * A request builder for user-supplied URLs: timeout, identifiable
     * user-agent and guarded redirects.
     */
    public function client(int $timeout = 20): PendingRequest
    {
        return Http::timeout($timeout)
            ->withUserAgent(self::userAgent())
            ->withOptions($this->redirectOptions());
    }

    public static function userAgent(): string
    {
        return 'ClientReporter/'.config('client-reporter.version', 'dev').' (+https://github.com/'.config('client-reporter.repository', 'coysh-digital/client-reporter').')';
    }

    private function isAllowListed(string $host): bool
    {
        if ((bool) config('client-reporter.outbound.allow_private', false)) {
            return true;
        }

        $allowed = array_map(
            fn ($entry): string => strtolower(trim((string) $entry)),
            (array) config('client-reporter.outbound.allowed_hosts', []),
        );

        return in_array($host, array_filter($allowed), true);
    }
}
