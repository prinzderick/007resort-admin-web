<?php

namespace Tests\Feature;

use App\Livewire\ApprovalsQueue;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\Fixtures as F;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    private const PERMS = ['payment.view', 'refund.execute', 'payment.reversal.execute', 'settlement.reconcile', 'refund.approve'];

    public function test_payments_list_with_filters_forwarded_to_the_api(): void
    {
        $this->fakeApi(['GET /payments' => F::page([F::payment(), F::payment('p2', 'AUTHORIZING', '5000.0000', ['tenderType' => 'CARD', 'provider' => 'PAYSTACK', 'providerReference' => 'PSK_1'])]), 'GET /organization/facilities' => F::facilities()]);

        $this->signIn(self::PERMS)->get('/finance/payments?filter[status]=CAPTURED&filter[facilityId]='.F::FAC.'&filter[from]=2026-09-01')->assertOk()
            ->assertSee('₦12,500.00')->assertSee('PSK_1')->assertSee('AUTHORIZING');

        $this->assertTrue($this->sentTo('GET', '/payments', fn (Request $r) => str_contains($r->url(), 'filter%5Bstatus%5D=CAPTURED') && str_contains($r->url(), 'filter%5Bfrom%5D=2026-09-01')));
    }

    public function test_payment_detail_offers_refund_and_reversal_by_permission(): void
    {
        $this->fakeApi(['GET /payments/*' => F::payment()]);

        $this->signIn(self::PERMS)->get('/finance/payments/'.F::PAY)->assertOk()->assertSee('Request refund')->assertSee('Request reversal');
        $this->signIn(['payment.view'])->get('/finance/payments/'.F::PAY)->assertOk()->assertDontSee('Request refund')->assertDontSee('Request reversal');
    }

    public function test_refund_that_needs_approval_is_shown_as_pending_not_done(): void
    {
        $this->fakeApi(['POST /payments/*/refund' => [202, ['status' => 'PENDING_APPROVAL', 'approval' => F::approval('appr-9')]], 'GET /payments/*' => F::payment()]);

        $res = $this->signIn(self::PERMS)->followingRedirects()->post('/finance/payments/'.F::PAY.'/refund', ['amount' => '2000.0000', 'reason' => 'Wrong item']);

        $res->assertOk()->assertSee('Awaiting approval')->assertSee('appr-9')->assertSee('Open approvals queue')->assertDontSee('Refund completed');
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/refund') && $r['amount'] === '2000.0000' && $r['reason'] === 'Wrong item' && $r->hasHeader('Idempotency-Key'));
    }

    public function test_refund_resource_with_pending_status_is_also_shown_as_pending(): void
    {
        $this->fakeApi(['POST /payments/*/refund' => [201, ['id' => 'r1', 'paymentId' => F::PAY, 'amount' => '2000.0000', 'status' => 'PENDING_APPROVAL', 'approvalId' => 'appr-1']], 'GET /payments/*' => F::payment()]);

        $this->signIn(self::PERMS)->followingRedirects()->post('/finance/payments/'.F::PAY.'/refund', ['amount' => '2000', 'reason' => 'x'])
            ->assertSee('Awaiting approval')->assertDontSee('Refund completed');
    }

    public function test_completed_refund_is_reported_as_done(): void
    {
        $this->fakeApi(['POST /payments/*/refund' => [201, ['id' => 'r1', 'paymentId' => F::PAY, 'amount' => '2000.0000', 'status' => 'COMPLETED']], 'GET /payments/*' => F::payment()]);

        $this->signIn(self::PERMS)->followingRedirects()->post('/finance/payments/'.F::PAY.'/refund', ['amount' => '2000', 'reason' => 'x'])
            ->assertSee('Refund completed.')->assertDontSee('Awaiting approval');
    }

    public function test_step_up_token_is_forwarded_as_a_header(): void
    {
        $this->fakeApi(['POST /payments/*/refund' => [201, ['status' => 'COMPLETED']], 'GET /payments/*' => F::payment()]);

        $this->signIn(self::PERMS)->post('/finance/payments/'.F::PAY.'/refund', ['amount' => '1', 'reason' => 'x', 'stepUpToken' => 'stp-1']);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->hasHeader('X-Step-Up-Token', 'stp-1'));
    }

    public function test_refund_validation_never_reaches_the_api_with_a_bad_amount(): void
    {
        $this->fakeApi([]);

        $this->signIn(self::PERMS)->post('/finance/payments/'.F::PAY.'/refund', ['amount' => '12.5abc', 'reason' => ''])->assertSessionHasErrors(['amount', 'reason']);
        $this->signIn(self::PERMS)->post('/finance/payments/'.F::PAY.'/refund', ['amount' => '0', 'reason' => 'x'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_api_business_rule_rejection_is_shown_back_on_the_form(): void
    {
        $this->fakeApi(['POST /payments/*/refund' => $this->problem(409, 'payment_state_invalid', 'Payment is already fully refunded.')]);

        $this->signIn(self::PERMS)->from('/finance/payments/'.F::PAY)->post('/finance/payments/'.F::PAY.'/refund', ['amount' => '10', 'reason' => 'x'])
            ->assertRedirect('/finance/payments/'.F::PAY)->assertSessionHas('error', fn ($m) => str_contains($m, 'Payment is already fully refunded.'));
    }

    public function test_forbidden_by_the_api_is_surfaced_even_if_the_ui_offered_the_action(): void
    {
        $this->fakeApi(['POST /payments/*/reversal' => $this->problem(403, 'permission_denied', 'Not allowed from this device.')]);

        $this->signIn(self::PERMS)->from('/finance/payments/x')->post('/finance/payments/'.F::PAY.'/reversal', ['reason' => 'dup'])->assertSessionHas('error');
    }

    public function test_reversal_pending_approval(): void
    {
        $this->fakeApi(['POST /payments/*/reversal' => [202, ['status' => 'PENDING_APPROVAL', 'approval' => F::approval('appr-2')]], 'GET /payments/*' => F::payment()]);

        $this->signIn(self::PERMS)->followingRedirects()->post('/finance/payments/'.F::PAY.'/reversal', ['reason' => 'dup'])->assertSee('Awaiting approval')->assertSee('appr-2');
    }

    public function test_reconciliation_groups_by_method_and_flags_unsettled_provider_payments(): void
    {
        $this->fakeApi(['GET /payments' => F::page([
            F::payment('a', 'CAPTURED', '10000.0000'),
            F::payment('b', 'CAPTURED', '5000.5000', ['tenderType' => 'CARD', 'provider' => 'PAYSTACK', 'providerReference' => 'PSK_A']),
            F::payment('c', 'AUTHORIZING', '7000.0000', ['tenderType' => 'CARD', 'provider' => 'PAYSTACK', 'providerReference' => 'PSK_B']),
            F::payment('d', 'PARTIALLY_REFUNDED', '3000.0000', ['refundedAmount' => '1000.0000']),
        ])]);

        $res = $this->signIn(self::PERMS)->get('/finance/reconciliation?from=2026-09-23&to=2026-09-23')->assertOk();

        $res->assertSee('₦13,000.00')   // CASH captured 10,000 + 3,000
            ->assertSee('₦5,000.50')    // CARD captured (7,000 AUTHORIZING excluded)
            ->assertSee('data-testid="unsettled"', false)->assertSee('1 provider payment(s) are not captured')->assertSee('PSK_B')
            ->assertSee('Bank settlement file');
    }

    public function test_paystack_verify_action(): void
    {
        $this->fakeApi(['GET /payments/paystack/verify/*' => ['reference' => 'PSK_B', 'status' => 'CAPTURED', 'amount' => '7000.0000']]);

        $this->signIn(self::PERMS)->from('/finance/reconciliation')->post('/finance/paystack-verify', ['reference' => 'PSK_B'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'CAPTURED') && str_contains($m, '₦7,000.00'));
    }

    public function test_paystack_reference_is_validated(): void
    {
        $this->fakeApi([]);
        $this->signIn(self::PERMS)->post('/finance/paystack-verify', ['reference' => '../../auth/me'])->assertSessionHasErrors('reference');
        Http::assertNothingSent();
    }

    public function test_approvals_queue_lists_pending_requests(): void
    {
        $this->fakeApi(['GET /approvals' => F::page([F::approval('a1'), F::approval('a2', 'PENDING')])]);
        $this->signIn(['refund.approve'])->get('/approvals')->assertOk()->assertSee('Refund 2,000 on payment')->assertSee('₦2,000.00')->assertSee('Approve')->assertSee('Reject');
        $this->assertTrue($this->sentTo('GET', '/approvals', fn (Request $r) => str_contains($r->url(), 'filter%5Bstatus%5D=PENDING')));
    }

    public function test_approver_approves_via_the_api(): void
    {
        $this->fakeApi(['GET /approvals' => F::page([F::approval('a1')]), 'POST /approvals/a1/decision' => F::approval('a1', 'APPROVED')]);
        $this->signIn(['refund.approve']);

        Livewire::test(ApprovalsQueue::class)->set('notes.a1', 'checked with till')->call('decide', 'a1', 'APPROVE')->assertSee('Approved. The action has been carried out by the API.');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/approvals/a1/decision') && $r['decision'] === 'APPROVE' && $r['note'] === 'checked with till' && $r->hasHeader('Idempotency-Key'));
    }

    public function test_reject_and_permission_denied_and_already_decided(): void
    {
        $this->fakeApi(['GET /approvals' => F::page([F::approval('a1')]), 'POST /approvals/a1/decision' => $this->problem(403, 'permission_denied'), 'POST /approvals/a2/decision' => $this->problem(409, 'concurrency_conflict')]);
        $this->signIn(['refund.approve']);

        Livewire::test(ApprovalsQueue::class)->call('decide', 'a1', 'REJECT')->assertSee('You do not hold the permission required to decide this request.')
            ->call('decide', 'a2', 'APPROVE')->assertSee('already decided');
    }

    public function test_decision_value_is_validated(): void
    {
        $this->fakeApi(['GET /approvals' => F::page([])]);
        $this->signIn(['refund.approve']);

        Livewire::test(ApprovalsQueue::class)->call('decide', 'a1', 'DELETE_EVERYTHING')->assertStatus(422);
    }

    public function test_approvals_degrade_when_the_endpoint_is_missing(): void
    {
        $this->fakeApi(['GET /approvals' => [501, []]]);
        $this->signIn(['refund.approve'])->get('/approvals')->assertOk()->assertSee('Approvals is not available: this API build', false);
    }
}
