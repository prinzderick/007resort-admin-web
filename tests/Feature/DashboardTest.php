<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fixtures as F;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    private const PERMS = ['report.view', 'order.view', 'booking.view', 'inventory.view', 'attendance.view', 'config.manage', 'refund.approve'];

    private function api(array $override = []): void
    {
        $this->fakeApi($override + [
            'GET /organization/facilities' => F::facilities(),
            'GET /reports/facility-daily-summary' => fn ($r) => F::summary($r['facilityId'] ?? (str_contains($r->url(), F::FAC2) ? F::FAC2 : F::FAC), str_contains($r->url(), F::FAC2) ? '50000.0000' : '100000.0000'),
            'GET /orders' => F::page([['id' => 'o1', 'status' => 'SENT'], ['id' => 'o2', 'status' => 'SENT'], ['id' => 'o3', 'status' => 'SETTLED']]),
            'GET /bookings' => F::page([['id' => 'b1', 'status' => 'CONFIRMED'], ['id' => 'b2', 'status' => 'HELD']]),
            'GET /inventory/items' => F::page([['id' => 'i1', 'name' => 'Star Lager', 'unit' => 'bottle', 'reorderLevel' => '48'], ['id' => 'i2', 'name' => 'Water', 'unit' => 'bottle', 'reorderLevel' => '10']]),
            'GET /inventory/balances' => F::page([['itemId' => 'i1', 'locationId' => 'l1', 'quantity' => '30'], ['itemId' => 'i1', 'locationId' => 'l2', 'quantity' => '10'], ['itemId' => 'i2', 'locationId' => 'l1', 'quantity' => '100']]),
            'GET /attendance' => F::page([['status' => 'OPEN'], ['status' => 'OPEN'], ['status' => 'CLOSED'], ['status' => 'NEEDS_REVIEW']]),
            'GET /approvals' => F::page([F::approval()]),
            'GET /system/health' => ['status' => 'ok', 'checks' => ['database' => 'ok']],
            'GET /sync/status' => ['node' => 'local', 'peerReachable' => true, 'lastHeartbeatAt' => now()->toIso8601ZuluString(), 'lastPeerHeartbeatAt' => now()->subSeconds(12)->toIso8601ZuluString(),
                'outbox' => ['queued' => 3, 'failed' => 1, 'conflict' => 0, 'oldestQueuedAt' => null], 'health' => 'ONLINE'],
        ]);
    }

    public function test_dashboard_shows_the_headline_figures_from_the_api(): void
    {
        $this->api();
        $res = $this->signIn(self::PERMS)->get('/')->assertOk();

        $res->assertSee('Dashboard')
            ->assertSee('₦150,000.00')                 // 100,000 + 50,000 net, from decimal strings
            ->assertSee('Main Restaurant')->assertSee('Pool Bar')
            ->assertSee('CASH')->assertSee('₦120,000.00') // cash 60k x 2
            ->assertSee('Star Lager')                   // 30+10=40 <= 48 reorder: low stock
            ->assertDontSee('Water (')
            ->assertSee('3 queued');
    }

    public function test_stock_indicator_flags_only_items_at_or_below_reorder_level(): void
    {
        $this->api();
        $html = $this->signIn(self::PERMS)->get('/')->getContent();

        $this->assertStringContainsString('data-testid="low-stock"', $html);
        $this->assertStringContainsString('Star Lager', substr($html, strpos($html, 'data-testid="low-stock"')));
    }

    public function test_fresh_data_is_labelled_live(): void
    {
        $this->api();
        $this->signIn(self::PERMS)->get('/')->assertSee('data-level="live"', false)->assertSee('Live as of');
    }

    public function test_stale_report_data_never_looks_live(): void
    {
        $this->api(['GET /reports/facility-daily-summary' => fn () => F::summary(F::FAC, '100000.0000', F::freshness(true, 'cloud'))]);
        $res = $this->signIn(self::PERMS)->get('/')->assertOk();

        $res->assertSee('data-level="stale"', false)->assertSee('STALE DATA')->assertSee('sync is behind')->assertSee('May be out of date');
        $this->assertStringNotContainsString('data-level="live"', $res->getContent());
    }

    public function test_offline_site_shows_as_of_last_sync_and_last_heartbeat(): void
    {
        $this->api(['GET /sync/status' => ['node' => 'cloud', 'peerReachable' => false, 'lastHeartbeatAt' => now()->toIso8601ZuluString(), 'lastPeerHeartbeatAt' => now()->subMinutes(7)->toIso8601ZuluString(),
            'outbox' => ['queued' => 42, 'failed' => 0, 'conflict' => 0, 'oldestQueuedAt' => null], 'health' => 'OFFLINE']]);
        $res = $this->signIn(self::PERMS)->get('/')->assertOk();

        $res->assertSee('SITE OFFLINE')->assertSee('as of the last sync')->assertSee('OFFLINE')->assertSee('7 minutes ago')->assertSee('As of last sync');
        $this->assertStringNotContainsString('data-level="live"', $res->getContent());
    }

    public function test_missing_freshness_block_is_unverified_not_live(): void
    {
        $this->api(['GET /reports/facility-daily-summary' => fn () => array_diff_key(F::summary(F::FAC), ['freshness' => 1])]);
        $this->signIn(['report.view'])->get('/')->assertSee('data-level="unknown"', false)->assertSee('UNVERIFIED');
    }

    public function test_no_data_at_all_is_not_live(): void
    {
        $this->fakeApi(['GET /organization/facilities' => F::facilities()]);
        $this->signIn(['report.view'])->get('/')->assertOk()->assertDontSee('data-level="live"', false);
    }

    public function test_each_block_degrades_on_its_own(): void
    {
        $this->api(['GET /inventory/*' => $this->problem(500, 'server_error'), 'GET /attendance' => [404, []], 'GET /orders' => $this->problem(403, 'permission_denied')]);
        $res = $this->signIn(self::PERMS)->get('/')->assertOk();

        $res->assertSee('₦150,000.00')                        // revenue still there
            ->assertSee('Stock could not be loaded', false)
            ->assertSee('Attendance is not available: this API build has not implemented', false)
            ->assertSee('Orders is hidden: your account lacks permission', false);
    }

    public function test_api_unreachable_still_renders_the_shell(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn () => throw new ConnectionException('down'));

        $this->signIn(self::PERMS)->get('/')->assertOk()->assertSee('API is unreachable', false);
    }

    public function test_a_user_without_report_permission_sees_no_revenue(): void
    {
        $this->api();
        $this->signIn(['order.view'])->get('/')->assertOk()->assertDontSee('Main Restaurant')->assertSee('Revenue and sales is hidden', false);
        $this->assertFalse($this->sentTo('GET', '/reports/facility-daily-summary'));
    }

    public function test_it_sees_queue_detail_but_others_get_coarse_status(): void
    {
        $this->api();
        $this->signIn(['report.view'])->get('/')->assertSee('visible to IT only')->assertSee('ONLINE');
    }
}
