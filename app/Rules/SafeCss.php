<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Keeps the branding "custom CSS" field to plain stylesheet rules. It is
 * injected verbatim into a <style> block on every client-facing report, so it
 * must never be able to close that block, embed markup, or make the renderer
 * fetch remote resources.
 */
final class SafeCss implements ValidationRule
{
    /** @var array<int, string> */
    private const FORBIDDEN = [
        '<',
        '>',
        '@import',
        '@charset',
        'url(',
        'expression(',
        'javascript:',
        'behavior:',
        '-moz-binding',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (! self::isSafe($value)) {
            $fail('The :attribute may only contain plain CSS rules: no markup, @import, url() or script-like values.');
        }
    }

    public static function isSafe(string $css): bool
    {
        // Compare against a decoded, lower-cased copy so escaped or mixed-case
        // variants of the forbidden tokens are caught too.
        $probe = strtolower(html_entity_decode($css, ENT_QUOTES | ENT_HTML5));
        $probe = preg_replace('/\\\\[0-9a-f]{1,6}\s?/i', '', $probe) ?? $probe;
        $probe = str_replace(['\\', "\0"], '', $probe);
        $probe = preg_replace('/\s+/', '', $probe) ?? $probe;

        foreach (self::FORBIDDEN as $needle) {
            if (str_contains($probe, str_replace(' ', '', $needle))) {
                return false;
            }
        }

        return true;
    }

    /**
     * The CSS if it is safe to render, otherwise null. Used when reading
     * values that were stored before validation existed (e.g. frozen renders).
     */
    public static function filter(?string $css): ?string
    {
        if ($css === null || trim($css) === '') {
            return null;
        }

        return self::isSafe($css) ? $css : null;
    }
}
