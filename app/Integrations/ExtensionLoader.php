<?php

declare(strict_types=1);

namespace App\Integrations;

use Illuminate\Support\Arr;

/**
 * Loads custom integrations that live in the (git-ignored) `extensions/`
 * directory, and from an optional local registration file. This is what keeps
 * a user's own integrations safe across updates: nothing here is tracked by the
 * core repository, so pulling a new release never touches it.
 *
 * An extension is just a Composer-package-shaped folder under `extensions/`:
 * a `composer.json` with a PSR-4 `autoload` map and an
 * `extra.client-reporter.integrations` list. It does not need to be installed
 * with Composer — its classes are autoloaded from disk and discovered here.
 */
class ExtensionLoader
{
    /**
     * Register PSR-4 autoloaders for every package under `extensions/`, so their
     * classes load without a `composer require`.
     */
    public static function registerAutoloaders(?string $extensionsDir = null): void
    {
        foreach (self::packageManifests($extensionsDir) as $packageDir => $manifest) {
            /** @var array<string, string> $psr4 */
            $psr4 = (array) Arr::get($manifest, 'autoload.psr-4', []);

            foreach ($psr4 as $prefix => $src) {
                $baseDir = $packageDir.'/'.trim((string) $src, '/').'/';

                spl_autoload_register(static function (string $class) use ($prefix, $baseDir): void {
                    if (! str_starts_with($class, $prefix)) {
                        return;
                    }

                    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
                    $file = $baseDir.$relative.'.php';

                    if (is_file($file)) {
                        require $file;
                    }
                });
            }
        }
    }

    /**
     * Integration classes declared by packages under `extensions/`.
     *
     * @return array<int, string>
     */
    public static function integrationClasses(?string $extensionsDir = null): array
    {
        $classes = [];

        foreach (self::packageManifests($extensionsDir) as $manifest) {
            foreach ((array) Arr::get($manifest, 'extra.client-reporter.integrations', []) as $class) {
                if (is_string($class) && $class !== '') {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    /**
     * Integration classes listed in the local registration file
     * (config/client-reporter.local.php), which is git-ignored.
     *
     * @return array<int, string>
     */
    public static function localIntegrationClasses(?string $path = null): array
    {
        $path ??= config_path('client-reporter.local.php');

        if (! is_file($path)) {
            return [];
        }

        $local = require $path;

        if (! is_array($local)) {
            return [];
        }

        return array_values(array_filter(
            (array) ($local['integrations'] ?? []),
            static fn ($class): bool => is_string($class) && $class !== '',
        ));
    }

    /**
     * The decoded composer.json of each package folder under `extensions/`,
     * keyed by the package's absolute directory.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function packageManifests(?string $extensionsDir = null): array
    {
        $extensionsDir ??= base_path('extensions');

        $manifests = [];

        foreach (glob($extensionsDir.'/*/composer.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);

            if (is_array($data)) {
                $manifests[dirname($file)] = $data;
            }
        }

        return $manifests;
    }
}
