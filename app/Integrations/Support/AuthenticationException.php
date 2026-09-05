<?php

declare(strict_types=1);

namespace App\Integrations\Support;

/**
 * The provider rejected the stored credentials (401/403): an expired or
 * revoked token, a rotated API key, lost permissions. Collection marks the
 * connection as needing re-authentication rather than retrying blindly.
 */
class AuthenticationException extends IntegrationException {}
