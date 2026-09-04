<?php

declare(strict_types=1);

namespace App\Integrations\Support;

use RuntimeException;

/**
 * Thrown by integrations/collectors to signal a failure with a message that is
 * safe to surface to agency staff (never containing credentials, tokens or raw
 * API internals). Unexpected non-IntegrationException failures are reported
 * generically instead.
 */
class IntegrationException extends RuntimeException {}
