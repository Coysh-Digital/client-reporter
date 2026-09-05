<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * Classifies IP addresses as publicly routable or not. Anything loopback,
 * private, link-local, carrier-grade NAT, multicast, reserved or documentation
 * is treated as non-public so the application never fetches from inside its
 * own network or a cloud metadata service on a user's behalf.
 */
final class IpRange
{
    /** @var array<int, string> */
    private const BLOCKED = [
        // IPv4
        '0.0.0.0/8',        // "this" network
        '10.0.0.0/8',       // private
        '100.64.0.0/10',    // carrier-grade NAT
        '127.0.0.0/8',      // loopback
        '169.254.0.0/16',   // link-local (includes cloud metadata)
        '172.16.0.0/12',    // private
        '192.0.0.0/24',     // IETF protocol assignments
        '192.0.2.0/24',     // documentation
        '192.168.0.0/16',   // private
        '198.18.0.0/15',    // benchmarking
        '198.51.100.0/24',  // documentation
        '203.0.113.0/24',   // documentation
        '224.0.0.0/4',      // multicast
        '240.0.0.0/4',      // reserved + broadcast
        // IPv6
        '::/128',           // unspecified
        '::1/128',          // loopback
        '64:ff9b::/96',     // NAT64 (unwrapped below, kept as belt and braces)
        '100::/64',         // discard
        '2001:db8::/32',    // documentation
        'fc00::/7',         // unique local
        'fe80::/10',        // link-local
        'ff00::/8',         // multicast
    ];

    public static function isPublic(string $ip): bool
    {
        $ip = trim($ip, '[] ');

        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        // IPv4-mapped (::ffff:a.b.c.d) and NAT64 (64:ff9b::a.b.c.d) addresses
        // carry an IPv4 address in their low 32 bits; judge that address.
        if (strlen($packed) === 16) {
            $embedded = self::embeddedIpv4($packed);
            if ($embedded !== null) {
                return self::isPublic($embedded);
            }
        }

        foreach (self::BLOCKED as $cidr) {
            if (self::contains($cidr, $ip)) {
                return false;
            }
        }

        return true;
    }

    public static function contains(string $cidr, string $ip): bool
    {
        [$network, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        $networkPacked = @inet_pton($network);
        $ipPacked = @inet_pton($ip);

        if ($networkPacked === false || $ipPacked === false || strlen($networkPacked) !== strlen($ipPacked)) {
            return false;
        }

        $bits = $bits === null ? strlen($ipPacked) * 8 : (int) $bits;
        $fullBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($fullBytes > 0 && substr($networkPacked, 0, $fullBytes) !== substr($ipPacked, 0, $fullBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($networkPacked[$fullBytes]) & $mask) === (ord($ipPacked[$fullBytes]) & $mask);
    }

    private static function embeddedIpv4(string $packed): ?string
    {
        $mapped = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
        $nat64 = "\x00\x64\xff\x9b\x00\x00\x00\x00\x00\x00\x00\x00";

        foreach ([$mapped, $nat64] as $prefix) {
            if (str_starts_with($packed, $prefix)) {
                return inet_ntop(substr($packed, 12)) ?: null;
            }
        }

        return null;
    }
}
