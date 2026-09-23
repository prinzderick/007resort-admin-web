<?php

namespace Tests\Unit;

use App\Auth\Mfa;
use App\Http\Controllers\ConfigurationController;
use App\Support\Contract;
use Tests\TestCase;

/**
 * Keeps the UI honest against the API contract: every endpoint the portal
 * declares it calls must exist in the contract snapshot, except the ones we
 * deliberately list as "waiting on the API" (screens gated by Contract::has).
 */
class ContractSnapshotTest extends TestCase
{
    /** Endpoints the portal is prepared for but the contract v1 does not define yet. */
    private function pending(): array
    {
        return [
            implode(' ', ConfigurationController::RULES_WRITE),
            implode(' ', ConfigurationController::RESOURCE_WRITE),
            implode(' ', Mfa::VERIFY),
        ];
    }

    public function test_snapshot_is_present_and_sane(): void
    {
        $this->assertNotNull(Contract::version());
        $this->assertTrue(Contract::has('POST', '/auth/staff/login'));
        $this->assertSame('config.manage', Contract::permission('GET', '/sync/status'));
        $this->assertFalse(Contract::has('GET', '/definitely/not/there'));
    }

    public function test_every_endpoint_the_ui_gates_on_is_in_the_contract_or_declared_pending(): void
    {
        $missing = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $f) {
            if ($f->getExtension() !== 'php') {
                continue;
            }
            preg_match_all("/\\['(GET|POST|PUT|PATCH|DELETE)', '(\\/[^']+)'\\]/", (string) file_get_contents($f->getPathname()), $m, PREG_SET_ORDER);
            foreach ($m as [, $method, $path]) {
                if (! Contract::has($method, $path) && ! in_array("$method $path", $this->pending(), true)) {
                    $missing[] = "$method $path (".basename($f->getPathname()).')';
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Endpoints used by the UI that are not in the API contract snapshot.');
    }

    public function test_pending_endpoints_are_really_absent_from_the_contract(): void
    {
        // When the API adds one of these, this fails: remove it from "pending" and drop the gate.
        foreach ($this->pending() as $key) {
            [$method, $path] = explode(' ', $key, 2);
            $this->assertFalse(Contract::has($method, $path), "$key is now in the contract; update the portal.");
        }
    }

    public function test_snapshot_matches_the_contract_when_it_is_available(): void
    {
        $spec = getenv('R007_CONTRACT_SPEC') ?: null;
        if (! $spec || ! is_file($spec)) {
            $this->markTestSkipped('Set R007_CONTRACT_SPEC=/path/to/api/openapi/v1.yaml to check the snapshot for drift.');
        }

        $before = file_get_contents(resource_path('contract/endpoints.json'));
        $this->artisan('r007:contract-sync', ['spec' => $spec])->assertSuccessful();
        $after = file_get_contents(resource_path('contract/endpoints.json'));
        file_put_contents(resource_path('contract/endpoints.json'), $before);

        $this->assertSame($before, $after, 'resources/contract/endpoints.json is out of date: run php artisan r007:contract-sync <spec>');
    }
}
