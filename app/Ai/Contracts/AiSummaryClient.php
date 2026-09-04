<?php

declare(strict_types=1);

namespace App\Ai\Contracts;

use App\Ai\Support\AiException;
use App\Ai\Support\AiMessages;
use App\Integrations\Support\VerificationResult;

/**
 * A minimal chat-completion client for one AI provider. Implementations speak
 * to their provider's HTTP API directly (no SDKs), return plain summary text,
 * and translate every failure into a safe {@see AiException}.
 */
interface AiSummaryClient
{
    /**
     * Produce a single completion for the given prompt pair.
     *
     * @throws AiException
     */
    public function complete(AiMessages $messages): string;

    /**
     * Test that the provider is reachable and the credentials are accepted.
     */
    public function verify(): VerificationResult;
}
