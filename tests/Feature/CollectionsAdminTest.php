<?php

namespace Tests\Feature;

use App\Auth\StaffSession;
use Tests\Support\RealApi;
use Tests\TestCase;

/**
 * The waiter-collection admin (docs/WAITER_COLLECTION.md) against recorded real data: the cashier's "waiting for confirmation" list with
 * confirm / reject, cash handovers with variance and sign-off, cash in hand, card machines, and the per-waiter cash policy.
 */
class CollectionsAdminTest extends TestCase
{
    private const WAITER = '1e21bd36-89eb-50c5-9b57-e343729f8e66';

    private function api(array $over = [], ?array $perms = null): void
    {
        $this->fakeApi($over + RealApi::routes(true));
        $this->signIn($perms ?? RealApi::ownerPermissions(), ['OWNER']);
    }

    public function test_pending_collections_show_what_to_check_and_offer_confirm_and_reject(): void
    {
        $this->api();
        $h = $this->get('/finance/collections')->assertOk()->assertSee('Collected by waiters')->assertSee('TRF-88231')->assertSee('A71834')->assertSee('Count the cash')->assertSee('Confirm')->assertSee('Reject')->getContent();
        $this->assertSame(3, substr_count($h, 'data-testid="confirm-btn"'));
        $this->assertStringContainsString('₦25,000.00', $h, 'total waiting');
        $this->assertTrue($this->sentTo('GET', '/payments', fn ($r) => str_contains($r->url(), 'status=PENDING_CONFIRMATION')));
    }

    public function test_filters_are_sent_as_the_apis_top_level_query(): void
    {
        $this->api();
        $this->get('/finance/collections?filter[status]=REJECTED&filter[facilityId]='.RealApi::FAC.'&filter[tenderType]=CASH&filter[collectedBy]='.self::WAITER)->assertOk();
        $this->assertTrue($this->sentTo('GET', '/payments', fn ($r) => str_contains($r->url(), 'status=REJECTED') && str_contains($r->url(), 'tenderType=CASH') && str_contains($r->url(), 'collectedBy='.self::WAITER)));
    }

    public function test_confirm_and_reject_post_to_the_payment_with_the_reason(): void
    {
        $id = RealApi::load('payments-pending-confirmation')['items'][0]['id'];
        $this->api(['POST /payments/*/confirm' => ['status' => 'CAPTURED'], 'POST /payments/*/reject' => ['status' => 'REJECTED']]);
        $this->post("/finance/collections/{$id}/confirm", ['matchedReference' => 'TRF-1', 'note' => 'Bank alert seen'])->assertRedirect('/finance/collections')->assertSessionHas('success', fn ($m) => str_contains($m, 'receipt'));
        $this->assertSame(['matchedReference' => 'TRF-1', 'note' => 'Bank alert seen'], $this->lastSent('POST', "payments/{$id}/confirm")->data());
        $this->post("/finance/collections/{$id}/reject", ['reason' => 'No alert'])->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'supervisor'));
        $this->assertSame(['reason' => 'No alert'], $this->lastSent('POST', "payments/{$id}/reject")->data());
        $this->post("/finance/collections/{$id}/reject", [])->assertSessionHasErrors('reason');
    }

    public function test_confirming_your_own_collection_is_refused_in_plain_words(): void
    {
        $id = RealApi::load('payments-pending-confirmation')['items'][0]['id'];
        $this->api(['POST /payments/*/confirm' => $this->problem(403, 'self_confirmation_forbidden', 'You collected this money yourself, so someone else must confirm it.')]);
        $this->from('/finance/collections')->post("/finance/collections/{$id}/confirm", [])->assertSessionHas('error', 'You collected this money yourself, so someone else must confirm it.');
    }

    public function test_the_collector_sees_no_confirm_button_on_their_own_money(): void
    {
        $this->api();
        $me = ['id' => self::WAITER, 'displayName' => 'Amaka', 'roles' => ['CASHIER'], 'permissions' => RealApi::ownerPermissions()];
        $h = $this->withSession([StaffSession::K_STAFF => $me])->get('/finance/collections')->assertOk()->assertSee('You collected this')->getContent();
        $this->assertSame(0, substr_count($h, 'data-testid="confirm-btn"'));
    }

    public function test_confirm_needs_payment_confirm_but_viewing_needs_only_payment_view(): void
    {
        $id = RealApi::load('payments-pending-confirmation')['items'][0]['id'];
        $this->api([], ['payment.view']);
        $h = $this->get('/finance/collections')->assertOk()->getContent();
        $this->assertSame(0, substr_count($h, 'data-testid="confirm-btn"'), 'viewers cannot act');
        $this->post("/finance/collections/{$id}/confirm", [])->assertForbidden();
        $this->post("/finance/collections/{$id}/reject", ['reason' => 'x'])->assertForbidden();
        $this->signIn(['order.view'])->get('/finance/collections')->assertForbidden();
    }

    public function test_handovers_show_variance_signoff_and_what_each_waiter_holds(): void
    {
        $this->api();
        $h = $this->get('/finance/handovers')->assertOk()->assertSee('Cash in hand')->assertSee('Needs supervisor sign-off')->assertSee('short')->assertSee('₦12,000.00')->assertSee('₦50,000.00')->assertSee('Sign off')->assertSee('Holding cash')->getContent();
        $this->assertStringContainsString('Amaka', $h);
        $this->assertTrue($this->sentTo('GET', '/staff/'.self::WAITER.'/cash-in-hand'));
    }

    public function test_receive_and_signoff_send_the_count_and_the_note(): void
    {
        $id = RealApi::load('cash-handovers')['items'][0]['id'];
        $this->api(['POST /cash-handovers/*/receive' => ['status' => 'PENDING_SIGNOFF'], 'POST /cash-handovers/*/signoff' => ['status' => 'RECEIVED']]);
        $this->post("/finance/handovers/{$id}/receive", ['countedAmount' => '6000.00', 'note' => 'Short'])->assertRedirect('/finance/handovers')->assertSessionHas('success', fn ($m) => str_contains($m, 'supervisor must sign it off'));
        $this->assertSame(['countedAmount' => '6000.00', 'note' => 'Short'], $this->lastSent('POST', "cash-handovers/{$id}/receive")->data());
        $this->post("/finance/handovers/{$id}/receive", ['countedAmount' => 'six'])->assertSessionHasErrors('countedAmount');
        $this->post("/finance/handovers/{$id}/signoff", ['note' => 'Change given twice'])->assertRedirect();
        $this->assertSame(['note' => 'Change given twice'], $this->lastSent('POST', "cash-handovers/{$id}/signoff")->data());
        $this->post("/finance/handovers/{$id}/signoff", [])->assertSessionHasErrors('note');
    }

    public function test_a_balanced_receipt_says_the_cash_in_hand_is_reduced(): void
    {
        $id = RealApi::load('cash-handovers')['items'][0]['id'];
        $this->api(['POST /cash-handovers/*/receive' => ['status' => 'RECEIVED']]);
        $this->post("/finance/handovers/{$id}/receive", ['countedAmount' => '7000'])->assertSessionHas('success', fn ($m) => str_contains($m, 'reduced by the declared amount'));
    }

    public function test_handover_actions_are_permission_gated(): void
    {
        $id = RealApi::load('cash-handovers')['items'][0]['id'];
        $this->api([], ['cash_handover.view']);
        $h = $this->get('/finance/handovers')->assertOk()->getContent();
        $this->assertStringNotContainsString('Sign off</button>', $h);
        $this->post("/finance/handovers/{$id}/receive", ['countedAmount' => '1'])->assertForbidden();
        $this->post("/finance/handovers/{$id}/signoff", ['note' => 'x'])->assertForbidden();
    }

    public function test_card_machines_registry_lists_creates_and_assigns(): void
    {
        $t = RealApi::load('payment-terminals')['items'][0];
        $this->api(['POST /payment-terminals' => [201, $t], 'PATCH /payment-terminals/*' => $t]);
        $this->get('/devices/payment-terminals')->assertOk()->assertSee('Bush Bar bank POS')->assertSee('DEMO-BUSH_BAR')->assertSee('Add card machine')->assertSee('Bank card machine');
        $this->post('/devices/payment-terminals', ['label' => 'Terrace machine', 'serial' => 'SN-1', 'facilityId' => RealApi::FAC, 'provider' => 'MANUAL_BANK', 'assignedStaffId' => self::WAITER])->assertRedirect('/devices/payment-terminals');
        $this->assertEqualsCanonicalizing(['label' => 'Terrace machine', 'serial' => 'SN-1', 'facilityId' => RealApi::FAC, 'provider' => 'MANUAL_BANK', 'assignedStaffId' => self::WAITER], $this->lastSent('POST', 'payment-terminals')->data());
        $this->patch('/devices/payment-terminals/'.$t['id'], ['label' => 'Bar machine', 'status' => 'INACTIVE', 'assignedStaffId' => '', 'rowVersion' => '1'])->assertRedirect();
        $req = $this->lastSent('PATCH', 'payment-terminals/'.$t['id']);
        $this->assertSame(['label' => 'Bar machine', 'status' => 'INACTIVE', 'assignedDeviceId' => null, 'assignedStaffId' => null], $req->data());
        $this->assertSame('"1"', $req->header('If-Match')[0]);
        $this->post('/devices/payment-terminals', ['label' => 'x', 'serial' => 'y', 'facilityId' => RealApi::FAC, 'provider' => 'NOPE'])->assertSessionHasErrors('provider');
    }

    public function test_card_machine_changes_need_device_manage(): void
    {
        $this->api([], ['payment.collect']);
        $this->get('/devices/payment-terminals')->assertForbidden(); // the devices area is device.* only; the API also refuses
        $this->signIn(['device.manage'])->get('/devices/payment-terminals')->assertOk();
        $this->signIn(['device.register'])->post('/devices/payment-terminals', [])->assertForbidden();
    }

    public function test_staff_page_shows_the_effective_policy_and_saves_the_override(): void
    {
        $this->api(['PATCH /staff/*/collection-policy' => RealApi::load('staff-collection-policy')]);
        $h = $this->get('/staff/'.self::WAITER)->assertOk()->assertSee('Cash collected at tables')->assertSee('May hold cash up to ₦50,000.00')->assertSee('from the facility rule')->assertSee('Follow the facility rule')->assertSee('Always allow')->assertSee('Never allow')->assertSee('Cash in hand')->getContent();
        $this->assertStringContainsString('name="cashHolding"', $h);
        $this->patch('/staff/'.self::WAITER.'/collection-policy', ['cashHolding' => 'ALLOW', 'cashLimit' => '80000.00'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(['cashHolding' => 'ALLOW', 'cashLimit' => '80000.00'], $this->lastSent('PATCH', 'staff/'.self::WAITER.'/collection-policy')->data());
        $this->patch('/staff/'.self::WAITER.'/collection-policy', ['cashHolding' => 'DENY', 'cashLimit' => '80000.00'])->assertRedirect();
        $this->assertNull($this->lastSent('PATCH', 'staff/'.self::WAITER.'/collection-policy')['cashLimit'], 'a personal limit only applies to ALLOW');
        $this->patch('/staff/'.self::WAITER.'/collection-policy', ['cashHolding' => 'MAYBE'])->assertSessionHasErrors('cashHolding');
    }

    public function test_waiter_collection_rules_are_on_the_facility_rules_form(): void
    {
        $this->api();
        $h = $this->get('/setup/facilities/'.RealApi::FAC.'?tab=rules')->assertOk()->getContent();
        foreach (['waiter_collection_enabled', 'waiter_cash_holding', 'waiter_cash_in_hand_limit', 'collection_requires_confirmation', 'pending_collection_expiry_minutes', 'pre_bill_requires_supervisor_if_reopened', 'bill_pay_link_enabled', 'cash_handover_max_variance'] as $k) {
            $this->assertStringContainsString('data-rule="'.$k.'"', $h);
        }
    }
}
