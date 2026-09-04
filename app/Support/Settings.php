<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Cached accessor for application-wide settings (agency information, mail
 * configuration, installation state, update-check timestamps). Registered as a
 * singleton in the container.
 */
class Settings
{
    private const CACHE_KEY = 'client-reporter.settings';

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(private readonly Cache $store) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();
        $this->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        return $this->cache = $this->store->rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all()
        );
    }

    public function flush(): void
    {
        $this->cache = null;
        $this->store->forget(self::CACHE_KEY);
    }

    /**
     * Whether the browser installation wizard has been completed.
     */
    public function isInstalled(): bool
    {
        return (bool) $this->get('installed', false);
    }
}
