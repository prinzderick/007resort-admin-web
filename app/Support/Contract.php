<?php

namespace App\Support;

/**
 * The API contract snapshot (resources/contract/endpoints.json, refreshed with
 * `php artisan r007:contract-sync`). Screens ask this before offering an
 * action, so an endpoint the contract does not (yet) define degrades to an
 * honest "not available yet" state instead of a broken button.
 */
final class Contract
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $index = null;

    public static function has(string $method, string $pathTemplate): bool
    {
        $key = strtoupper($method).' '.$pathTemplate;

        if (in_array($key, (array) config('r007.disabled_endpoints', []), true)) {
            return false;
        }

        return isset(self::index()[$key]);
    }

    public static function permission(string $method, string $pathTemplate): ?string
    {
        return self::index()[strtoupper($method).' '.$pathTemplate]['permission'] ?? null;
    }

    public static function version(): ?string
    {
        return self::load()['contractVersion'] ?? null;
    }

    /** @return array<string, array<string, mixed>> */
    public static function index(): array
    {
        if (self::$index === null) {
            self::$index = [];
            foreach (self::load()['endpoints'] ?? [] as $e) {
                self::$index[$e['method'].' '.$e['path']] = $e;
            }
        }

        return self::$index;
    }

    /**
     * Test seam: pretend the contract also defines these "METHOD /path" keys.
     *
     * @param  list<string>  $keys
     */
    public static function fake(array $keys): void
    {
        $index = self::index();
        foreach ($keys as $k) {
            $index[$k] = ['method' => strtok($k, ' '), 'path' => substr($k, strpos($k, ' ') + 1), 'permission' => null];
        }
        self::$index = $index;
    }

    public static function flush(): void
    {
        self::$index = null;
    }

    /** @return array<string, mixed> */
    private static function load(): array
    {
        $file = resource_path('contract/endpoints.json');

        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
    }
}
