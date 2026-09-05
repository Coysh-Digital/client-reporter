<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A small, dependency-free TOTP implementation (RFC 6238) plus the helpers the
 * two-factor flow needs: a base32 secret, an otpauth:// provisioning URI, and
 * one-time recovery codes. Kept deliberately minimal — 30-second steps, 6
 * digits, SHA-1 — which is what every authenticator app defaults to.
 */
class TwoFactor
{
    private const PERIOD = 30;

    private const DIGITS = 6;

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * A fresh base32 TOTP secret (160 bits, the recommended length).
     */
    public static function generateSecret(): string
    {
        // 32 base32 chars = 160 bits, drawn uniformly from the alphabet.
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * The otpauth:// URI an authenticator app consumes (also fine to show as a
     * QR later). Issuer and account both appear in the app's entry name.
     */
    public static function otpauthUri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);

        return 'otpauth://totp/'.$label.'?'.$query;
    }

    /**
     * Verify a 6-digit code against the secret, allowing one step of clock drift
     * either way (so a code from the previous or next 30-second window passes).
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $counter = (int) floor(time() / self::PERIOD);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::code($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The current (or a given moment's) TOTP code for a secret. Handy for
     * previewing during setup and for tests.
     */
    public static function codeAt(string $secret, ?int $timestamp = null): string
    {
        return self::code($secret, (int) floor(($timestamp ?? time()) / self::PERIOD));
    }

    /**
     * A set of one-time recovery codes (plaintext; store them hashed).
     *
     * @return array<int, string>
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(3))).'-'.strtoupper(bin2hex(random_bytes(3)));
        }

        return $codes;
    }

    /**
     * The HOTP value for a counter, formatted to the configured digit count.
     */
    private static function code(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $binCounter = pack('N*', 0).pack('N*', $counter); // 64-bit big-endian
        $hash = hash_hmac('sha1', $binCounter, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper(trim($secret));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::BASE32_ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $index;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
