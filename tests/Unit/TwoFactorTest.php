<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TwoFactor;
use PHPUnit\Framework\TestCase;

class TwoFactorTest extends TestCase
{
    /** RFC 6238's test secret: ASCII "12345678901234567890" in base32. */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_it_matches_the_rfc_6238_test_vectors(): void
    {
        // 6-digit truncations of the published SHA-1 vectors.
        $this->assertSame('287082', TwoFactor::codeAt(self::RFC_SECRET, 59));
        $this->assertSame('081804', TwoFactor::codeAt(self::RFC_SECRET, 1111111109));
        $this->assertSame('050471', TwoFactor::codeAt(self::RFC_SECRET, 1111111111));
    }

    public function test_verify_accepts_the_current_code_and_rejects_others(): void
    {
        $secret = TwoFactor::generateSecret();

        $this->assertTrue(TwoFactor::verify($secret, TwoFactor::codeAt($secret)));
        $this->assertFalse(TwoFactor::verify($secret, '000000'));
        $this->assertFalse(TwoFactor::verify($secret, 'not-a-code'));
    }

    public function test_a_code_from_a_far_window_is_rejected(): void
    {
        $secret = TwoFactor::generateSecret();
        // Ten steps ago is well outside the ±1 window.
        $stale = TwoFactor::codeAt($secret, time() - (30 * 10));

        $this->assertFalse(TwoFactor::verify($secret, $stale));
    }

    public function test_generated_secret_is_32_base32_chars(): void
    {
        $secret = TwoFactor::generateSecret();

        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_recovery_codes_are_unique_and_formatted(): void
    {
        $codes = TwoFactor::generateRecoveryCodes(8);

        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[0-9A-F]{6}-[0-9A-F]{6}$/', $code);
        }
    }

    public function test_otpauth_uri_carries_the_secret_and_issuer(): void
    {
        $uri = TwoFactor::otpauthUri('SECRET', 'user@example.com', 'Acme');

        $this->assertStringStartsWith('otpauth://totp/Acme:user%40example.com?', $uri);
        $this->assertStringContainsString('secret=SECRET', $uri);
        $this->assertStringContainsString('issuer=Acme', $uri);
    }
}
