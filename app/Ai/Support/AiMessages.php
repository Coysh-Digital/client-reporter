<?php

declare(strict_types=1);

namespace App\Ai\Support;

/**
 * The system + user prompt pair sent to an AI provider for one completion.
 */
readonly class AiMessages
{
    public function __construct(
        public string $system,
        public string $user,
    ) {}
}
