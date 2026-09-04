<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Safely updates keys in the .env file during installation. If the file is not
 * writable (some shared hosts), the caller can fall back to showing the user the
 * lines to paste in themselves.
 */
class EnvWriter
{
    public function __construct(private readonly string $path) {}

    public function isWritable(): bool
    {
        return is_file($this->path) ? is_writable($this->path) : is_writable(dirname($this->path));
    }

    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public function write(array $values): bool
    {
        if (! $this->isWritable()) {
            return false;
        }

        $contents = is_file($this->path) ? (string) file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            $contents = $this->set($contents, $key, $value);
        }

        return file_put_contents($this->path, $contents) !== false;
    }

    /**
     * The .env lines for a set of values (for copy/paste when not writable).
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public function preview(array $values): string
    {
        return collect($values)
            ->map(fn ($value, string $key): string => $key.'='.$this->format($value))
            ->implode("\n");
    }

    private function set(string $contents, string $key, string|int|bool|null $value): string
    {
        $line = $key.'='.$this->format($value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents)) {
            return (string) preg_replace($pattern, $line, $contents);
        }

        return rtrim($contents, "\n")."\n".$line."\n";
    }

    private function format(string|int|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        // Quote values containing spaces or special characters.
        if ($value === '' || preg_match('/\s|#|"/', $value)) {
            return '"'.addcslashes($value, '"\\').'"';
        }

        return $value;
    }
}
