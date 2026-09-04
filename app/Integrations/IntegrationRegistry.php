<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Integrations\Contracts\Integration;
use App\Integrations\Support\IntegrationCategory;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Discovers and holds the available integrations. First-party integrations come
 * from config('client-reporter.integrations'); third-party integrations are
 * discovered from installed Composer packages that declare an
 * "extra.client-reporter.integrations" array. Both are treated identically.
 */
class IntegrationRegistry
{
    /** @var array<int, class-string<Integration>> */
    private array $classes = [];

    /** @var array<string, Integration>|null */
    private ?array $resolved = null;

    /**
     * @param  array<int, class-string<Integration>>  $classes
     */
    public function __construct(array $classes = [])
    {
        foreach ($classes as $class) {
            $this->register($class);
        }
    }

    /**
     * @param  class-string<Integration>  $class
     */
    public function register(string $class): void
    {
        if (! is_subclass_of($class, Integration::class)) {
            throw new InvalidArgumentException("[{$class}] must extend ".Integration::class.'.');
        }

        if (! in_array($class, $this->classes, true)) {
            $this->classes[] = $class;
            $this->resolved = null;
        }
    }

    /**
     * All integrations keyed by their manifest key.
     *
     * @return array<string, Integration>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $resolved = [];

        foreach ($this->classes as $class) {
            /** @var Integration $instance */
            $instance = app($class);
            $resolved[$instance->key()] = $instance;
        }

        return $this->resolved = $resolved;
    }

    public function find(string $key): ?Integration
    {
        return $this->all()[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /**
     * The keys of all integrations in a category.
     *
     * @return array<int, string>
     */
    public function keysInCategory(IntegrationCategory $category): array
    {
        return array_values(array_map(
            fn (Integration $i): string => $i->key(),
            array_filter($this->all(), fn (Integration $i): bool => $i->manifest()->category === $category),
        ));
    }

    /**
     * Integrations grouped by category in display order.
     *
     * @return array<string, array<int, Integration>>
     */
    public function byCategory(): array
    {
        $grouped = [];

        foreach (IntegrationCategory::ordered() as $category) {
            $items = array_values(array_filter(
                $this->all(),
                fn (Integration $i): bool => $i->manifest()->category === $category
            ));

            if ($items !== []) {
                $grouped[$category->value] = $items;
            }
        }

        return $grouped;
    }

    /**
     * Read integration class names declared by installed Composer packages.
     *
     * @return array<int, class-string<Integration>>
     */
    public static function discoverFromComposer(?string $installedJsonPath = null): array
    {
        $path = $installedJsonPath ?? base_path('vendor/composer/installed.json');

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        $packages = $data['packages'] ?? $data ?? [];

        return Collection::make($packages)
            ->flatMap(fn (array $package): array => (array) data_get($package, 'extra.client-reporter.integrations', []))
            ->filter(fn ($class): bool => is_string($class) && is_subclass_of($class, Integration::class))
            ->values()
            ->all();
    }
}
