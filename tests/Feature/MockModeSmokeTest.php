<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * R007_MOCK=true: the whole portal runs on fixtures with NO backend. Http is
 * forbidden here, so any accidental call to a real API fails the test.
 */
class MockModeSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['r007.mock' => true, 'r007.mock_scenario' => 'normal']);
        Cache::flush();
        Http::preventStrayRequests();
    }

    private function login(string $who = 'owner')
    {
        return $this->post('/login', ['identifier' => $who, 'password' => 'password']);
    }

    public function test_login_page_advertises_mock_mode(): void
    {
        $this->get('/login')->assertOk()->assertSee('MOCK DATA')->assertSee('password');
    }

    public function test_bad_password_is_rejected(): void
    {
        $this->post('/login', ['identifier' => 'owner', 'password' => 'nope'])->assertSessionHasErrors('identifier');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_owner_can_open_every_screen(): void
    {
        $this->login()->assertRedirect('/');

        $paths = [
            '/', '/approvals', '/reports', '/reports/facility/0192f6a0-7b1c-7d2e-9a3b-000000000101', '/reports/shift/0192f6a0-7b1c-7d2e-9a3b-000000000700',
            '/finance/payments', '/finance/payments/0192f6a0-7b1c-7d2e-9a3b-000000000800', '/finance/settlements', '/finance/refunds', '/finance/cash-sessions', '/operations/orders', '/people/roles',
            '/inventory', '/inventory/receive', '/inventory/transfer', '/inventory/adjust', '/inventory/wastage', '/inventory/count',
            '/staff', '/staff/0192f6a0-7b1c-7d2e-9a3b-000000000201', '/staff/attendance', '/system/audit',
            '/setup', '/setup/facilities', '/setup/facilities/0192f6a0-7b1c-7d2e-9a3b-000000000101', '/setup/catalog', '/setup/business', '/setup/memberships', '/setup/bookings', '/setup/tickets', '/setup/kds', '/setup/payments',
            '/devices', '/sync',
        ];
        foreach ($paths as $p) {
            $this->get($p)->assertOk();
        }
    }

    public function test_cashier_sees_a_reduced_portal(): void
    {
        $this->login('cashier');
        $this->get('/')->assertOk()->assertDontSee('Sync &amp; IT', false)->assertDontSee('Business &amp; receipts', false)->assertDontSee('Membership plans');
        $this->get('/finance/payments')->assertForbidden();
        $this->get('/sync')->assertForbidden();
    }

    public function test_offline_scenario_never_looks_live(): void
    {
        config(['r007.mock_scenario' => 'offline']);
        $this->login();
        $this->get('/')->assertOk()->assertSee('data-level="offline"', false)->assertDontSee('data-level="live"', false);
    }

    public function test_stale_scenario_is_flagged(): void
    {
        config(['r007.mock_scenario' => 'stale', 'r007.instance' => 'cloud']);
        $this->login();
        $this->get('/')->assertOk()->assertSee('data-level="stale"', false);
    }
}
