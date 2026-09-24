<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Snapshot the endpoints (method, path, permission) of the API contract
 * (007resort-docs api/openapi/v1.yaml) into resources/contract/endpoints.json.
 *
 * The UI consults that snapshot to decide which screens/actions can be live
 * and which must show a "not yet in the API" state, so the portal never calls
 * or fakes an endpoint the contract does not define.
 */
class ContractSync extends Command
{
    protected $signature = 'r007:contract-sync {spec : Path to api/openapi/v1.yaml}';

    protected $description = 'Refresh resources/contract/endpoints.json from the OpenAPI contract';

    public function handle(): int
    {
        $spec = (string) $this->argument('spec');

        if (! is_file($spec)) {
            $this->error("Spec not found: {$spec}");

            return self::FAILURE;
        }

        try {
            /** @var array<string, mixed> $doc */
            $doc = Yaml::parseFile($spec);
            $endpoints = [];

            foreach (($doc['paths'] ?? []) as $path => $ops) {
                foreach ($ops as $method => $op) {
                    if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                        continue;
                    }
                    $endpoints[] = [
                        'method' => strtoupper($method),
                        'path' => $path,
                        'operationId' => $op['operationId'] ?? null,
                        'permission' => $op['x-permission'] ?? null,
                    ];
                }
            }
            $version = $doc['info']['version'] ?? null;
        } catch (ParseException) {
            // The generated spec can list a path twice (the "additive" operations are appended
            // as a second `/x:` block), which a strict YAML parser rejects. Scan it line by line.
            [$endpoints, $version] = $this->scan((string) file_get_contents($spec));
        }

        $endpoints = array_values(array_filter($endpoints, fn ($e) => ! str_starts_with((string) $e['path'], '/iclock'))); // device push protocol

        usort($endpoints, fn ($a, $b) => [$a['path'], $a['method']] <=> [$b['path'], $b['method']]);

        file_put_contents(resource_path('contract/endpoints.json'), json_encode([
            'contractVersion' => $version,
            'endpoints' => $endpoints,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info(count($endpoints).' endpoints written (contract '.($version ?? '?').').');

        return self::SUCCESS;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: ?string}
     */
    private function scan(string $yaml): array
    {
        $endpoints = [];
        $seen = [];
        $version = null;
        $inPaths = false;
        $path = $method = null;

        foreach (preg_split('/\R/', $yaml) as $line) {
            if (preg_match('/^  version:\s*[\'"]?([^\'"\s]+)/', $line, $m) && $version === null) {
                $version = $m[1];
            }
            if (preg_match('/^(\S[^:]*):/', $line, $m)) {
                $inPaths = $m[1] === 'paths';
                $path = $method = null;

                continue;
            }
            if (! $inPaths) {
                continue;
            }
            if (preg_match('/^  (\/[^:\s]*):\s*$/', $line, $m)) {
                $path = $m[1];
                $method = null;
            } elseif ($path !== null && preg_match('/^    (get|post|put|patch|delete):\s*$/', $line, $m)) {
                $method = strtoupper($m[1]);
                $key = $method.' '.$path;
                $seen[$key] = count($endpoints);
                $endpoints[] = ['method' => $method, 'path' => $path, 'operationId' => null, 'permission' => null];
            } elseif ($method !== null && preg_match('/^      operationId:\s*(\S+)/', $line, $m)) {
                $endpoints[array_key_last($endpoints)]['operationId'] = $m[1];
            } elseif ($method !== null && preg_match('/^      x-permission:\s*(\S+)/', $line, $m)) {
                $endpoints[array_key_last($endpoints)]['permission'] = $m[1];
            }
        }

        return [$endpoints, $version];
    }
}
