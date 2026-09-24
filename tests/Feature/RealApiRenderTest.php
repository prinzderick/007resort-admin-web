<?php

namespace Tests\Feature;

use App\Livewire\SyncCenter;
use App\Support\Contract;
use Livewire\Livewire;
use Tests\Support\RealApi;
use Tests\TestCase;

/**
 * Renders EVERY page against JSON recorded from the real API, as the owner and as every seeded role. An undefined-key crash,
 * a swallowed API error or a page that only worked against the mock backend cannot ship again.
 */
class RealApiRenderTest extends TestCase
{
    private function crashed(string $html): ?string
    {
        foreach (['Undefined array key', 'Undefined variable', 'Undefined property', 'Attempt to read property', 'Whoops', 'ErrorException', 'TypeError'] as $needle) {
            if (str_contains($html, $needle)) {
                return $needle;
            }
        }

        return null;
    }

    public function test_the_owner_can_open_every_screen_and_nothing_is_reported_as_an_error(): void
    {
        $this->fakeApi(RealApi::routes(true));
        Contract::fake(['PUT /facilities/{facilityId}/capabilities', 'PUT /facilities/{facilityId}/operating-rules', 'PATCH /organization/facilities/{facilityId}', 'POST /organization/facilities']);
        $this->signIn(RealApi::ownerPermissions(), ['OWNER']);

        foreach (RealApi::pages() as $path) {
            $res = $this->get($path);
            $body = $res->getContent();
            $this->assertSame(200, $res->status(), "GET {$path} answered ".$res->status());
            $this->assertNull($this->crashed($body), "GET {$path} crashed: ".$this->crashed($body));
            // "pending" (endpoint not in the contract) is honest; "error" / "missing" mean the portal asked for something the API did not answer.
            $this->assertStringNotContainsString('data-state="error"', $body, "GET {$path} shows a failed read");
            $this->assertStringNotContainsString('data-state="missing"', $body, "GET {$path} reads an endpoint the API does not have");
        }
    }

    public function test_every_seeded_role_gets_a_page_or_a_clean_403_never_a_crash(): void
    {
        $this->fakeApi(RealApi::routes(true));
        foreach (RealApi::rolePermissions() as $code => $perms) {
            $this->signIn($perms, [$code]);
            foreach (RealApi::pages() as $path) {
                $res = $this->get($path);
                $this->assertContains($res->status(), [200, 403], "{$code}: GET {$path} answered ".$res->status());
                if ($res->status() === 200) {
                    $this->assertNull($this->crashed($res->getContent()), "{$code}: GET {$path} crashed: ".$this->crashed($res->getContent()));
                }
            }
        }
    }

    public function test_csv_exports_work_on_real_data(): void
    {
        $this->fakeApi(RealApi::routes());
        $this->signIn(RealApi::ownerPermissions(), ['OWNER']);
        foreach (['/reports?format=csv', '/reports/facility/'.RealApi::FAC.'?format=csv', '/finance/payments?format=csv', '/finance/refunds?format=csv', '/finance/cash-sessions?format=csv', '/inventory?format=csv', '/system/audit?format=csv', '/staff/attendance?format=csv', '/operations/orders?format=csv', '/operations/bookings?format=csv'] as $p) {
            $r = $this->get($p);
            $r->assertOk();
            $this->assertStringContainsString('text/csv', (string) $r->headers->get('Content-Type'), $p);
        }
    }

    public function test_sync_centre_tabs_work_on_real_outbox_and_a_conflict(): void
    {
        $this->fakeApi(RealApi::routes());
        $this->signIn(RealApi::ownerPermissions(), ['OWNER']);
        foreach (['health', 'outbox', 'inbox', 'conflicts'] as $tab) {
            Livewire::test(SyncCenter::class)->set('tab', $tab)->assertOk()->assertDontSee('could not be loaded');
        }
        Livewire::test(SyncCenter::class)->set('tab', 'conflicts')->assertSee('CONFIGURATION', false)->assertSee('both nodes changed the rule');
    }
}
