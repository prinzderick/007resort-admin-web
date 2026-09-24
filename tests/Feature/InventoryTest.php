<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fixtures as F;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    private const ITEM = '0192f6a0-7b1c-7d2e-9a3b-000000000500';

    private const LOC = '0192f6a0-7b1c-7d2e-9a3b-000000000600';

    private const LOC2 = '0192f6a0-7b1c-7d2e-9a3b-000000000601';

    private const PERMS = ['inventory.view', 'inventory.purchase_receipt.create', 'inventory.transfer.create', 'inventory.adjustment.request', 'inventory.wastage.create', 'inventory.count.create', 'inventory.count.post'];

    private function api(array $o = []): void
    {
        $this->fakeApi($o + [
            'GET /inventory/items' => F::page([['id' => self::ITEM, 'sku' => 'B1', 'name' => 'Star Lager', 'unit' => 'bottle', 'reorderLevel' => '48']]),
            'GET /inventory/locations' => F::page([['id' => self::LOC, 'name' => 'Main Store', 'kind' => 'MAIN_STORE'], ['id' => self::LOC2, 'name' => 'Pool Bar Store', 'kind' => 'BAR']]),
            'GET /inventory/balances' => F::page([['itemId' => self::ITEM, 'itemName' => 'Star Lager', 'locationId' => self::LOC, 'quantity' => '240', 'unit' => 'bottle', 'reorderLevel' => '48.0000', 'belowReorder' => false, 'updatedAt' => '2026-09-23T08:00:00.000000Z'],
                ['itemId' => self::ITEM, 'itemName' => 'Star Lager', 'locationId' => self::LOC2, 'quantity' => '30.5000', 'unit' => 'bottle', 'reorderLevel' => '48.0000', 'belowReorder' => true, 'updatedAt' => '2026-09-23T08:00:00.000000Z']]),
        ]);
    }

    public function test_balances_by_location_flag_low_stock(): void
    {
        $this->api();
        $res = $this->signIn(self::PERMS)->get('/inventory')->assertOk();

        $res->assertSee('Main Store')->assertSee('Pool Bar Store')->assertSee('240 bottle')->assertSee('1 line(s) at or below reorder level');
    }

    public function test_location_filter_is_sent_to_the_api(): void
    {
        $this->api();
        $this->signIn(self::PERMS)->get('/inventory?location='.self::LOC)->assertOk();
        $this->assertTrue($this->sentTo('GET', '/inventory/balances', fn (Request $r) => str_contains($r->url(), 'locationId='.self::LOC)));
    }

    public function test_balances_csv(): void
    {
        $this->api();
        $csv = $this->signIn(self::PERMS)->get('/inventory?format=csv')->streamedContent();
        $this->assertStringContainsString('"Star Lager","Pool Bar Store",30.5000,bottle,48', $csv);
    }

    public function test_only_permitted_actions_are_offered(): void
    {
        $this->api();
        $this->signIn(['inventory.view', 'inventory.count.create'])->get('/inventory')->assertSee('Stock count')->assertDontSee('Receive stock')->assertDontSee('Adjust stock');
    }

    public function test_receive_stock_posts_lines_to_the_api(): void
    {
        $this->api(['POST /inventory/purchase-receipts' => [201, ['id' => 'm1', 'kind' => 'PURCHASE_RECEIPT', 'status' => 'POSTED', 'lines' => []]]]);

        $this->signIn(self::PERMS)->post('/inventory/receive', ['locationId' => self::LOC, 'supplierName' => 'Acme', 'lines' => [
            1 => ['itemId' => self::ITEM, 'quantity' => '24', 'unitCost' => '650.5000'], 2 => ['itemId' => '', 'quantity' => '', 'unitCost' => ''],
        ]])->assertRedirect('/inventory')->assertSessionHas('success', 'Stock received.');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/inventory/purchase-receipts') && $r['locationId'] === self::LOC && $r['supplierName'] === 'Acme'
            && $r['lines'] === [['itemId' => self::ITEM, 'quantity' => '24', 'unitCost' => '650.5000']] && $r->hasHeader('Idempotency-Key'));
    }

    public function test_transfer_validates_distinct_locations(): void
    {
        $this->api();
        $this->signIn(self::PERMS)->post('/inventory/transfer', ['fromLocationId' => self::LOC, 'toLocationId' => self::LOC, 'lines' => [1 => ['itemId' => self::ITEM, 'quantity' => '1']]])->assertSessionHasErrors('fromLocationId');
    }

    public function test_transfer_insufficient_stock_is_shown_from_the_api(): void
    {
        $this->api(['POST /inventory/transfers' => $this->problem(409, 'insufficient_stock', 'Only 30 on hand.')]);
        $this->signIn(self::PERMS)->from('/inventory/transfer')->post('/inventory/transfer', ['fromLocationId' => self::LOC2, 'toLocationId' => self::LOC, 'lines' => [1 => ['itemId' => self::ITEM, 'quantity' => '99']]])
            ->assertRedirect('/inventory/transfer')->assertSessionHas('error', fn ($m) => str_contains($m, 'Only 30 on hand.'));
    }

    public function test_adjustment_needing_approval_is_shown_as_pending(): void
    {
        $this->api(['POST /inventory/adjustments' => [202, ['status' => 'PENDING_APPROVAL', 'approval' => F::approval('adj-1')]]]);

        $res = $this->signIn(self::PERMS)->followingRedirects()->post('/inventory/adjust', ['locationId' => self::LOC2, 'itemId' => self::ITEM, 'quantityDelta' => '-12', 'reason' => 'DAMAGE', 'note' => 'Broken in delivery']);

        $res->assertSee('Awaiting approval')->assertSee('adj-1')->assertDontSee('Adjustment posted');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/inventory/adjustments') && $r['quantityDelta'] === '-12' && $r['reason'] === 'DAMAGE');
    }

    public function test_adjustment_requires_a_note_and_valid_reason(): void
    {
        $this->api();
        $this->signIn(self::PERMS)->post('/inventory/adjust', ['locationId' => self::LOC, 'itemId' => self::ITEM, 'quantityDelta' => 'abc', 'reason' => 'LOL', 'note' => ''])->assertSessionHasErrors(['quantityDelta', 'reason', 'note']);
    }

    public function test_wastage(): void
    {
        $this->api(['POST /inventory/wastage' => [201, ['id' => 'm', 'status' => 'POSTED', 'kind' => 'WASTAGE', 'lines' => []]]]);
        $this->signIn(self::PERMS)->post('/inventory/wastage', ['locationId' => self::LOC, 'itemId' => self::ITEM, 'quantity' => '2', 'reason' => 'SPOILAGE'])->assertSessionHas('success', 'Wastage recorded.');
    }

    public function test_count_shows_variance_then_posts(): void
    {
        $count = ['id' => 'cnt-1', 'locationId' => self::LOC, 'status' => 'DRAFT', 'lines' => [['itemId' => self::ITEM, 'expectedQuantity' => '240.0000', 'countedQuantity' => '236.0000', 'variance' => '-4.0000']]];
        $posted = ['status' => 'POSTED'] + $count;
        $this->api(['POST /inventory/counts' => [201, $count], 'POST /inventory/counts/cnt-1/post' => [200, $posted], 'GET /inventory/counts/cnt-1' => $count]);
        $this->signIn(self::PERMS);

        $this->post('/inventory/count', ['locationId' => self::LOC, 'lines' => [1 => ['itemId' => self::ITEM, 'countedQuantity' => '236']]])->assertRedirect('/inventory/count/cnt-1');
        $this->get('/inventory/count/cnt-1')->assertOk()->assertSee('Variance')->assertSee('-4.0000')->assertSee('Star Lager')->assertSee('Post count');

        $this->post('/inventory/count/cnt-1/post')->assertRedirect('/inventory/count/cnt-1');
        $this->api(['GET /inventory/counts/cnt-1' => $posted]);
        $this->get('/inventory/count/cnt-1')->assertSee('POSTED')->assertDontSee('Post count');
    }

    public function test_posting_a_count_needs_its_own_permission(): void
    {
        $this->api();
        $this->signIn(['inventory.view', 'inventory.count.create'])->post('/inventory/count/cnt-1/post')->assertForbidden();
    }

    public function test_movements_history_reads_the_ledger(): void
    {
        $this->api(['GET /inventory/movements' => F::page([['id' => 'm1', 'itemId' => self::ITEM, 'locationId' => self::LOC, 'kind' => 'CONSUMPTION', 'reason' => 'SALE', 'quantityDelta' => '-1.0000', 'balanceAfter' => '239.0000', 'createdAt' => '2026-09-23T08:00:00.000Z', 'actorStaffId' => null]])]);
        $this->signIn(self::PERMS)->get('/inventory/movements')->assertOk()->assertSee('Star Lager')->assertSee('-1.0000')->assertSee('239.0000');
    }
}
