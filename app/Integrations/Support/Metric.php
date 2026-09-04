<?php

declare(strict_types=1);

namespace App\Integrations\Support;

/**
 * A single normalised metric value produced by a collector for a period, e.g.
 * analytics.visitors = 1240. Stored in the `metrics` table for fast comparison
 * and dashboard cards. The metric key is dotted and namespaced by the domain
 * (analytics.*, uptime.*, ecommerce.*, cms.*) so different sources can be
 * compared where meaningful while retaining their source connection.
 */
readonly class Metric
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $key,
        public int|float $value,
        public ?string $unit = null,
        public array $meta = [],
    ) {}
}
