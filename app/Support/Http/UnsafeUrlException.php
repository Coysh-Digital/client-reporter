<?php

declare(strict_types=1);

namespace App\Support\Http;

use InvalidArgumentException;

/**
 * Thrown when a user-supplied URL points somewhere the application must not
 * fetch from (a non-HTTP scheme, or a private/internal address). The message
 * is safe to show to staff.
 */
final class UnsafeUrlException extends InvalidArgumentException {}
