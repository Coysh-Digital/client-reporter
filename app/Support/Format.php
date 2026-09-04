<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Presentation helpers for client-facing reports: human-friendly numbers,
 * durations and previous-period comparisons.
 */
class Format
{
    public static function number(int|float|null $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, $decimals);
    }

    public static function money(int|float|null $value, ?string $currency = null): string
    {
        if ($value === null) {
            return '—';
        }

        $symbols = ['GBP' => '£', 'USD' => '$', 'EUR' => '€', 'AUD' => 'A$', 'CAD' => 'C$'];
        $symbol = $symbols[strtoupper((string) $currency)] ?? '';
        $decimals = fmod((float) $value, 1.0) === 0.0 ? 0 : 2;

        $formatted = $symbol.number_format((float) $value, $decimals);

        return $symbol !== '' ? $formatted : trim($formatted.' '.strtoupper((string) $currency));
    }

    public static function percent(int|float|null $value, int $decimals = 2): string
    {
        if ($value === null) {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $value, $decimals), '0'), '.').'%';
    }

    /**
     * Human-friendly duration from seconds (e.g. "2h 5m", "45s", "0").
     */
    public static function duration(int|float|null $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $seconds = (int) $seconds;

        if ($seconds <= 0) {
            return 'None';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }
        if ($secs > 0 && $days === 0 && $hours === 0) {
            $parts[] = "{$secs}s";
        }

        return implode(' ', $parts) ?: '0s';
    }

    /**
     * Format a raw metric value per its declared display type — the single
     * source of truth shared by the metric-grid partial and auto-generated
     * insight sentences, so a value always reads the same way everywhere.
     */
    public static function forType(int|float|null $value, string $fmt, ?string $currency = null): string
    {
        return match ($fmt) {
            'percent' => self::percent($value, 0),
            'percent1' => self::percent($value, 1),
            'uptime' => self::percent($value, 2),
            'decimal1' => self::number($value, 1),
            'money' => self::money($value, $currency),
            'ms' => $value === null ? '—' : self::number($value).' ms',
            'duration' => self::duration($value),
            default => self::number($value),
        };
    }

    /**
     * Compare a value against the previous period.
     *
     * @return array{direction: string, percent: ?float, absolute: ?float}
     */
    public static function change(int|float|null $current, int|float|null $previous): array
    {
        if ($current === null || $previous === null) {
            return ['direction' => 'none', 'percent' => null, 'absolute' => null];
        }

        $absolute = $current - $previous;
        $direction = match (true) {
            $absolute > 0 => 'up',
            $absolute < 0 => 'down',
            default => 'flat',
        };

        $percent = $previous != 0.0 ? ($absolute / abs($previous)) * 100 : null;

        return ['direction' => $direction, 'percent' => $percent, 'absolute' => $absolute];
    }
}
