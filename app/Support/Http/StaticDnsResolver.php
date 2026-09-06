<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * A fixed hostname map for tests. Unknown hosts resolve to a public address so
 * faked HTTP calls pass the outbound guard without any network access.
 */
final class StaticDnsResolver implements DnsResolver
{
    /**
     * @param  array<string, array<int, string>>  $map  host => addresses
     */
    public function __construct(
        private readonly array $map = [],
        private readonly string $default = '93.184.216.34',
    ) {}

    public function resolve(string $host): array
    {
        return $this->map[strtolower($host)] ?? [$this->default];
    }
}
