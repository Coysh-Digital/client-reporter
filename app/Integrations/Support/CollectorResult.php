<?php

declare(strict_types=1);

namespace App\Integrations\Support;

/**
 * The output of one collector run: a set of normalised metrics (persisted to
 * the `metrics` table) plus an optional richer snapshot payload (persisted to
 * `metric_snapshots` for tables/charts like top pages or incident lists).
 */
class CollectorResult
{
    /** @var array<int, Metric> */
    private array $metrics = [];

    /** @var array<string, mixed> */
    private array $snapshot = [];

    private string $granularity = 'range';

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function metric(string $key, int|float $value, ?string $unit = null, array $meta = []): self
    {
        $this->metrics[] = new Metric($key, $value, $unit, $meta);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function snapshot(array $payload): self
    {
        $this->snapshot = array_merge($this->snapshot, $payload);

        return $this;
    }

    public function granularity(string $granularity): self
    {
        $this->granularity = $granularity;

        return $this;
    }

    /**
     * @return array<int, Metric>
     */
    public function metrics(): array
    {
        return $this->metrics;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotPayload(): array
    {
        return $this->snapshot;
    }

    public function hasSnapshot(): bool
    {
        return $this->snapshot !== [];
    }

    public function granularityValue(): string
    {
        return $this->granularity;
    }
}
