<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * Resolves a hostname to its IP addresses. Bound in the container so tests can
 * substitute a fixed map instead of touching the network.
 */
interface DnsResolver
{
    /**
     * Every A/AAAA address the host resolves to, or an empty array when it
     * does not resolve.
     *
     * @return array<int, string>
     */
    public function resolve(string $host): array;
}
