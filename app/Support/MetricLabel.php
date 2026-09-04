<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns a dotted metric key (e.g. "analytics.bounce_rate") into a human label
 * ("Bounce Rate") for quick at-a-glance display. Report blocks still choose
 * their own wording; this is for generic, unlabelled metric listings.
 */
class MetricLabel
{
    public static function for(string $key): string
    {
        $tail = str_contains($key, '.') ? Str::afterLast($key, '.') : $key;

        return Str::of($tail)->replace('_', ' ')->title()->toString();
    }
}
