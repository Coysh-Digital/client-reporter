<?php

declare(strict_types=1);

namespace App\Integrations\Contracts;

use App\Support\Settings;
use Illuminate\Support\Str;

/**
 * Convenience base for collectors: derives a sane key from the class name and
 * provides a default cadence. Override {@see intervalMinutes()} as needed.
 */
abstract class AbstractCollector implements Collector
{
    public function key(): string
    {
        return (string) Str::of(class_basename($this))
            ->beforeLast('Collector')
            ->snake();
    }

    public function intervalMinutes(): int
    {
        return (int) app(Settings::class)->get(
            'collection_interval',
            config('client-reporter.collection.default_interval', 360),
        );
    }
}
