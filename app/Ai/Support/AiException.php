<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Integrations\Support\IntegrationException;

/**
 * Thrown by AI clients to signal a failure with a message that is safe to show
 * staff — never containing the API key, request body or raw provider internals.
 */
class AiException extends IntegrationException {}
