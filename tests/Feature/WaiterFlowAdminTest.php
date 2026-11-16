<?php

namespace Tests\Feature;

use Tests\Support\RealApi;
use Tests\TestCase;

/** Waiter-only service flow in the portal: open tabs board, pay on behalf, reassign, hand-over history, receipt print queue. */
class WaiterFlowAdminTest extends TestCase
{
    private const WAITER = '1e21bd36-89eb-50c5-9b57-e343729f8e66';

    private const ORDER = '01a13776-0000-7000-8000-000000000001';

    private function api(array $over = [], array $extraPerms = ['waiterflow.manage', 'receipt.print']): void
    {
        $board = ['facilityId' => RealApi::FAC, 'scope' => 'ALL', 'groups' => [[
            'key' => 'k1', 'tabId' => null, 'tableId' => null, 'tableLabel' => 'T4', 'customerName' => 'Mr Ade', 'ownerStaffId' => self::WAITER, 'ownerName' => 'Amaka Okoro',
            'orders' => [['id' => self::ORDER, 'number' => 'PB-000012', 'status' => 'SERVED', 'balance' => '9000.0000', 'total' => '9000.0000', 'paid' => '0.0000', 'canPay' => true, 'lines' => [['name' => 'Chapman', 'quantity' => 2]]]],
            'total' => '9000.0000', 'paid' => '0.0000', 'balance' => '9000.0000', 'oldestAt' => '2026-10-13T10:00:00.000Z', 'allServed' => true, 'handoverPending' => false,
        ]], 'waiters' => [['staffId' => self::WAITER, 'name' => 'Amaka Okoro', 'tabs' => 1, 'orders' => 1, 'balance' => '9000.0000']], 'totals' => ['tabs' => 1, 'orders' => 1, 'balance' => '9000.0000']];
        $this->fakeApi($over + ['GET /waiter-flow/open-items' => $board] + RealApi::routes(true));
        $this->signIn(array_merge(RealApi::ownerPermissions(), $extraPerms), ['OWNER']);
    }

    public function test_open_tabs_board_shows_every_unpaid_tab_with_its_waiter(): void
    {
        $this->api();
        $h = $this->get('/operations/open-tabs')->assertOk()->assertSee('Open tabs')->assertSee('Amaka Okoro')->assertSee('T4')->assertSee('Take payment')->assertSee('Reassign')->getContent();
        $this->assertStringContainsString('2 x Chapman', $h);
        $this->assertTrue($this->sentTo('GET', '/waiter-flow/open-items', fn ($r) => str_contains($r->url(), 'facilityId='.RealApi::FAC)));
    }

    public function test_paying_on_a_waiters_behalf_posts_the_order_ids_and_one_tender(): void
    {
        $this->api(['POST /waiter-flow/settle' => ['receiptId' => 'r1', 'orders' => [], 'closedTabIds' => []]]);
        $this->post('/operations/open-tabs/pay', ['facilityId' => RealApi::FAC, 'orderIds' => self::ORDER, 'tenderType' => 'CASH', 'amount' => '9000'])
            ->assertRedirect()->assertSessionHas('success');
        $sent = $this->lastSent('POST', 'waiter-flow/settle')->data();
        $this->assertSame([self::ORDER], $sent['orderIds']);
        $this->assertSame('CASH', $sent['tenders'][0]['tenderType']);
        $this->assertSame('9000', $sent['tenders'][0]['amount']);
    }

    public function test_card_and_transfer_need_a_reference_before_anything_is_sent(): void
    {
        $this->api();
        $this->post('/operations/open-tabs/pay', ['facilityId' => RealApi::FAC, 'orderIds' => self::ORDER, 'tenderType' => 'TRANSFER', 'amount' => '9000'])->assertSessionHasErrors('reference');
        $this->assertFalse($this->sentTo('POST', '/waiter-flow/settle'));
    }

    public function test_reassign_needs_a_reason(): void
    {
        $this->api(['POST /waiter-flow/reassign' => ['status' => 'FORCED']]);
        $to = '2a9a8c6e-0000-5000-8000-000000000002';
        $this->post('/operations/open-tabs/reassign', ['facilityId' => RealApi::FAC, 'orderIds' => self::ORDER, 'toStaffId' => $to, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->post('/operations/open-tabs/reassign', ['facilityId' => RealApi::FAC, 'orderIds' => self::ORDER, 'toStaffId' => $to, 'reason' => 'left early'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('left early', $this->lastSent('POST', 'waiter-flow/reassign')->data()['reason']);
    }

    public function test_pages_need_the_right_permission(): void
    {
        $this->api([], []);
        $this->signIn(['order.view'])->get('/operations/open-tabs')->assertForbidden();
        $this->signIn(['order.view'])->get('/operations/print-queue')->assertForbidden();
    }

    public function test_print_queue_lists_jobs_and_the_job_page_shows_the_receipt_lines_and_marks_it_printed(): void
    {
        $job = ['id' => '01a13777-0000-7000-8000-0000000000aa', 'receiptNumber' => 'RC-0001', 'requestedBy' => 'Amaka Okoro', 'copies' => 1, 'status' => 'QUEUED', 'createdAt' => '2026-10-13T10:05:00.000Z',
            'receipt' => ['printLines' => ['SERI RESORT', 'Chapman x2     9,000.00', 'TOTAL         9,000.00']]];
        $this->api(['GET /print-jobs' => ['items' => [$job]], 'GET /print-jobs/*' => $job, 'POST /print-jobs/*/printed' => ['status' => 'PRINTED']]);
        $this->get('/operations/print-queue')->assertOk()->assertSee('RC-0001')->assertSee('Print');
        $this->get('/operations/print-queue/'.$job['id'])->assertOk()->assertSee('SERI RESORT')->assertSee('TOTAL         9,000.00', false);
        $this->post('/operations/print-queue/'.$job['id'].'/printed')->assertRedirect(route('print-queue'));
        $this->assertTrue($this->sentTo('POST', '/print-jobs/'.$job['id'].'/printed'));
    }
}
