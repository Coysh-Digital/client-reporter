<?php

declare(strict_types=1);

namespace App\Support;

use App\Importers\ImporterException;
use App\Integrations\Support\IntegrationException;
use App\Support\Http\UnsafeUrlException;
use Throwable;

/**
 * Turns a throwable into a message that is safe to store and show to agency
 * staff. Only exceptions the application raised with a deliberately written
 * message pass through; anything else (a Guzzle error carrying a signed URL,
 * a driver exception with a DSN) is reduced to its class name.
 */
final class SafeError
{
    private const MAX_LENGTH = 500;

    public static function message(Throwable $e, string $fallback = 'Unexpected error'): string
    {
        if ($e instanceof IntegrationException || $e instanceof ImporterException || $e instanceof UnsafeUrlException) {
            return mb_substr($e->getMessage(), 0, self::MAX_LENGTH);
        }

        return $fallback.' ('.class_basename($e).').';
    }

    /**
     * The safe first line of a stored exception trace (e.g. from failed_jobs),
     * which is "Class: message" for framework-formatted traces.
     */
    public static function fromTrace(string $trace): string
    {
        $first = trim((string) strtok($trace, "\n"));

        if ($first === '') {
            return 'Unexpected error.';
        }

        [$class] = array_pad(explode(':', $first, 2), 2, '');
        $class = trim($class);

        $trusted = [IntegrationException::class, ImporterException::class, UnsafeUrlException::class];
        foreach ($trusted as $type) {
            if ($class === $type || is_subclass_of($class, $type)) {
                return mb_substr($first, 0, self::MAX_LENGTH);
            }
        }

        return 'Unexpected error ('.class_basename($class ?: 'Error').').';
    }
}
