<?php

declare(strict_types=1);

namespace App\Integrations\Support;

/**
 * The provider is throttling us (429). The credentials are fine; the next
 * scheduled collection will simply try again later.
 */
class RateLimitedException extends IntegrationException {}
