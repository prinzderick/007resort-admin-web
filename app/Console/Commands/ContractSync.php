<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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

        /** @var array<string, mixed> $doc */
        $doc = Yaml::parseFile($spec);
        $endpoints = [];

        foreach (($doc['paths'] ?? []) as $path => $ops) {
            if (str_starts_with((string) $path, '/iclock')) {
                continue; // device push protocol, not an admin concern
            }
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

        usort($endpoints, fn ($a, $b) => [$a['path'], $a['method']] <=> [$b['path'], $b['method']]);

        file_put_contents(resource_path('contract/endpoints.json'), json_encode([
            'contractVersion' => $doc['info']['version'] ?? null,
            'endpoints' => $endpoints,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info(count($endpoints).' endpoints written (contract '.($doc['info']['version'] ?? '?').').');

        return self::SUCCESS;
    }
}
