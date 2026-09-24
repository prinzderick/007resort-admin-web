<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Services\Portal\Directory;
use App\Support\Contract;
use App\Support\Fetch;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Money that waiters collected at the table (docs/WAITER_COLLECTION.md): the cashier's "waiting for confirmation" list with
 * confirm / reject, and cash handovers (received, variance, supervisor sign-off) with the cash each waiter still holds.
 * A waiter can never mark a bill paid: everything they collect stays pending until someone with payment.confirm verifies it.
 */
class CollectionsController extends Controller
{
    public const STATUSES = [
        'PENDING_CONFIRMATION' => 'Waiting for confirmation', 'AUTHORIZING' => 'Waiting for the payment provider', 'CAPTURED' => 'Confirmed',
        'REJECTED' => 'Rejected', 'EXPIRED' => 'Expired', 'CANCELLED' => 'Cancelled',
    ];

    public const TENDERS = ['CASH' => 'Cash', 'CARD_TERMINAL' => 'Card machine', 'TRANSFER' => 'Bank transfer', 'PAY_LINK' => 'Pay link'];

    public function index(Request $request, DashboardData $dash, Directory $dir)
    {
        $filter = array_filter((array) $request->query('filter', []), fn ($v) => is_string($v) && $v !== '');
        $status = $filter['status'] ?? 'PENDING_CONFIRMATION';
        $params = ['limit' => 100, 'cursor' => $request->query('cursor'), 'status' => $status];
        foreach (['facilityId', 'collectedBy', 'tenderType'] as $k) {
            isset($filter[$k]) && $params[$k] = $filter[$k];
        }
        $payments = Fetch::of(fn () => $this->api->get('payments', $params), ['GET', '/payments']);
        // Only waiter collections carry the `collection` block; a plain cashier payment with the same status is not listed here.
        $rows = array_values(array_filter($payments->items(), fn ($p) => ! empty($p['collection'])));
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());

        // Newer APIs send orderNumber, tableLabel, collectedByName and terminalLabel with every collection (no lookups needed).
        // Older ones do not: fall back to reading the order and the table list, at most 40 rows.
        $orders = [];
        foreach (array_slice($rows, 0, 40) as $p) {
            $oid = $p['allocations'][0]['orderId'] ?? null;
            if ($oid && ! isset($orders[$oid]) && ! isset($p['collection']['orderNumber'])) {
                $o = Fetch::of(fn () => $this->api->get("orders/{$oid}"), ['GET', '/orders/{orderId}']);
                $orders[$oid] = $o->ok() ? ['number' => $o->data['number'] ?? null, 'tableId' => $o->data['tableId'] ?? null, 'balanceDue' => $o->data['balanceDue'] ?? null] : [];
            }
        }
        $tableLabels = [];
        foreach (array_unique(array_filter(array_map(fn ($p) => isset($p['collection']['tableLabel']) || isset($p['collection']['orderNumber']) ? null : ($p['facilityId'] ?? null), $rows))) as $fid) {
            foreach ($this->all('tables', ['facilityId' => $fid], ['GET', '/tables'], 1)->items() as $t) {
                $tableLabels[$t['id'] ?? ''] = $t['label'] ?? '';
            }
        }

        $pendingTotal = '0.0000';
        foreach ($rows as $p) {
            if (($p['status'] ?? '') === 'PENDING_CONFIRMATION') {
                $pendingTotal = Money::add($pendingTotal, (string) ($p['amount'] ?? '0'));
            }
        }

        return view('pages.finance.collections', [
            'payments' => $payments, 'rows' => $rows, 'filter' => $filter, 'status' => $status, 'facilities' => $flat, 'facilityNames' => collect($flat)->pluck('name', 'id')->all(),
            'staffNames' => $dir->staffNames(), 'orders' => $orders, 'tableLabels' => $tableLabels, 'pendingTotal' => $pendingTotal, 'canConfirm' => $this->staff->can('payment.confirm'),
            'myId' => $this->staff->id(), 'statuses' => self::STATUSES, 'tenders' => self::TENDERS,
        ]);
    }

    public function confirm(Request $request, string $payment): RedirectResponse
    {
        abort_unless($this->staff->can('payment.confirm'), 403);
        $d = $request->validate(['matchedReference' => ['nullable', 'string', 'max:120'], 'note' => ['nullable', 'string', 'max:500']]);
        $this->api->request('POST', "payments/{$payment}/confirm", [], array_filter($d, fn ($v) => $v !== null && $v !== ''));

        return redirect()->route('finance.collections')->with('success', 'Confirmed. The bill is settled and the receipt is issued.');
    }

    public function reject(Request $request, string $payment): RedirectResponse
    {
        abort_unless($this->staff->can('payment.confirm'), 403);
        $d = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->api->request('POST', "payments/{$payment}/reject", [], ['reason' => $d['reason']]);

        return redirect()->route('finance.collections')->with('success', 'Rejected. The bill is payable again and a supervisor has been alerted.');
    }

    // ---- cash handovers ---------------------------------------------------------------------------------------------------

    public function handovers(Request $request, DashboardData $dash, Directory $dir)
    {
        $status = (string) $request->query('status', '');
        $handovers = $this->all('cash-handovers', array_filter(['status' => $status ?: null, 'facilityId' => $request->query('facilityId') ?: null]), ['GET', '/cash-handovers'], 2);
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());
        // Cash each waiter holds right now. Newer APIs list them per facility in one call; older ones need one call per waiter.
        $inHand = [];
        $selling = array_filter($flat, fn ($f) => in_array('TABLE_SERVICE', (array) ($f['capabilities'] ?? []), true));
        if (Contract::has('GET', '/cash-in-hand')) {
            foreach (array_slice($selling, 0, 12) as $f) {
                $r = Fetch::of(fn () => $this->api->get('cash-in-hand', ['facilityId' => $f['id']]), ['GET', '/cash-in-hand']);
                foreach ($r->items() as $h) {
                    if (! empty($h['staffId'])) {
                        $inHand[$h['staffId']] = (array) $h + ['facilityName' => $f['name'] ?? ''];
                    }
                }
            }
        } else {
            $pending = Fetch::of(fn () => $this->api->get('payments', ['status' => 'PENDING_CONFIRMATION', 'limit' => 100]), ['GET', '/payments']);
            $waiters = collect($handovers->items())->pluck('waiterStaffId')->merge(collect($pending->items())->filter(fn ($p) => ! empty($p['collection']))->pluck('collection.collectedByStaffId'))->filter()->unique()->take(30);
            foreach ($waiters as $w) {
                $r = Fetch::of(fn () => $this->api->get("staff/{$w}/cash-in-hand"), ['GET', '/staff/{staffId}/cash-in-hand']);
                $r->ok() && $inHand[$w] = (array) $r->data;
            }
        }

        return view('pages.finance.handovers', [
            'handovers' => $handovers, 'status' => $status, 'inHand' => $inHand, 'staffNames' => $dir->staffNames(), 'facilities' => $flat, 'facilityNames' => collect($flat)->pluck('name', 'id')->all(),
            'canReceive' => $this->staff->can('cash_handover.receive'), 'canSignoff' => $this->staff->can('cash_handover.signoff'), 'myId' => $this->staff->id(),
        ]);
    }

    public function receive(Request $request, string $handover): RedirectResponse
    {
        abort_unless($this->staff->can('cash_handover.receive'), 403);
        $d = $request->validate(['countedAmount' => ['required', 'regex:/^\d{1,15}(\.\d{1,4})?$/'], 'note' => ['nullable', 'string', 'max:500']]);
        $res = $this->api->request('POST', "cash-handovers/{$handover}/receive", [], array_filter($d, fn ($v) => $v !== null && $v !== ''));
        $status = $res->body['status'] ?? '';

        return redirect()->route('finance.handovers')->with('success', $status === 'PENDING_SIGNOFF'
            ? 'Received. The counted cash differs from what was declared by more than the allowed variance, so a supervisor must sign it off.'
            : 'Handover received. The waiter\'s cash in hand is reduced by the declared amount.');
    }

    public function signoff(Request $request, string $handover): RedirectResponse
    {
        abort_unless($this->staff->can('cash_handover.signoff'), 403);
        $d = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $this->api->request('POST', "cash-handovers/{$handover}/signoff", [], ['note' => $d['note']]);

        return redirect()->route('finance.handovers')->with('success', 'Variance signed off.');
    }
}
