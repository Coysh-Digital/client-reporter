<?php

declare(strict_types=1);

namespace App\Integrations\Support;

/**
 * The outcome of testing a connection's credentials against its external
 * service. A failure carries a user-friendly message (never a raw stack trace
 * or API internals) explaining what to fix.
 */
readonly class VerificationResult
{
    /**
     * @param  array<string, mixed>  $meta  non-sensitive details to persist (e.g. connector_version)
     */
    private function __construct(
        public bool $ok,
        public string $message,
        public array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(string $message = 'Connected successfully.', array $meta = []): self
    {
        return new self(true, $message, $meta);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
