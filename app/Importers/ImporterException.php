<?php

declare(strict_types=1);

namespace App\Importers;

use RuntimeException;

/**
 * Thrown when an importer cannot reach or authenticate with an external
 * platform, or receives an unusable response. The message is safe to show the
 * operator (no credentials).
 */
class ImporterException extends RuntimeException {}
