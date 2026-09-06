<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * Resolves hostnames through the operating system's resolver.
 */
final class SystemDnsResolver implements DnsResolver
{
    public function resolve(string $host): array
    {
        $addresses = [];

        foreach ([DNS_A => 'ip', DNS_AAAA => 'ipv6'] as $type => $field) {
            $records = @dns_get_record($host, $type);

            if (! is_array($records)) {
                continue;
            }

            foreach ($records as $record) {
                if (isset($record[$field]) && is_string($record[$field])) {
                    $addresses[] = $record[$field];
                }
            }
        }

        // dns_get_record() ignores /etc/hosts; gethostbyname() honours it, which
        // matters for "localhost" style names on self-hosted installs.
        if ($addresses === []) {
            $fallback = gethostbyname($host);

            if ($fallback !== $host) {
                $addresses[] = $fallback;
            }
        }

        return array_values(array_unique($addresses));
    }
}
