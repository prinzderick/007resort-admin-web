<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Services\Portal\Directory;
use App\Support\Fetch;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Waiter-only service flow (docs/WAITER_ONLY_FLOW.md in the API): every open tab with the waiter looking after it, taking payment
 * on a waiter's behalf, reassigning, the hand-over history, and the receipt print queue (receipts a tablet asked to print).
 * The tablet does the day-to-day work; this is the supervisor/owner view and the safety net.
 */
class WaiterFlowController extends Controller
{
    public const TENDERS = ['CASH' => 'Cash', 'POS_TERMINAL' => 'Card / POS machine', 'TRANSFER' => 'Bank transfer'];

    public const HANDOVER_STATUSES = ['PENDING' => 'Waiting to be accepted', 'ACCEPTED' => 'Accepted', 'REJECTED' => 'Rejected', 'CANCELLED' => 'Cancelled', 'EXPIRED' => 'Expired', 'FORCED' => 'Reassigned by a supervisor'];

    /** @return array{0: list<array<string, mixed>>, 1: string} facilities that serve tables, and the one chosen */
    private function facilities(Request $request, DashboardData $dash): array
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = array_values(array_filter($dash->flatten($tree->items()), fn ($f) => in_array('TABLE_SERVICE', (array) ($f['capabilities'] ?? []), true)));
        $chosen = (string) $request->query('facilityId', '');
        if ($chosen === '' || ! collect($flat)->contains('id', $chosen)) {
            $chosen = (string) ($flat[0]['id'] ?? '');
        }

        return [$flat, $chosen];
    }

    public function openTabs(Request $request, DashboardData $dash, Directory $dir)
    {
        [$facilities, $facilityId] = $this->facilities($request, $dash);
        $board = $facilityId === '' ? null : Fetch::of(fn () => $this->api->get('waiter-flow/open-items', ['facilityId' => $facilityId, 'staffId' => $request->query('staffId')]), ['GET', '/waiter-flow/open-items']);
        $waiters = [];
        foreach ((array) ($board?->data['waiters'] ?? []) as $w) {
            $waiters[$w['staffId']] = $w['name'] ?? $w['staffId'];
        }

        return view('pages.operations.open-tabs', [
            'facilities' => $facilities, 'facilityId' => $facilityId, 'board' => $board, 'staffId' => (string) $request->query('staffId', ''),
            'waiters' => $waiters, 'staffNames' => $dir->staffNames(), 'tenders' => self::TENDERS,
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'facilityId' => ['required', 'uuid'], 'orderIds' => ['required', 'string'], 'tenderType' => ['required', 'in:CASH,POS_TERMINAL,TRANSFER'],
            'amount' => ['required', 'numeric', 'gt:0'], 'reference' => ['nullable', 'string', 'max:128'],
        ]);
        $ids = array_values(array_filter(explode(',', $d['orderIds'])));
        $tender = ['tenderType' => $d['tenderType'], 'amount' => Money::norm((string) $d['amount'])];
        if ($d['tenderType'] === 'CASH') {
            $tender['tendered'] = $tender['amount'];
        } else {
            $tender['reference'] = trim((string) ($d['reference'] ?? ''));
            if ($tender['reference'] === '') {
                return back()->withErrors(['reference' => 'Enter the card slip or transfer reference.'])->withInput();
            }
        }
        $res = $this->api->request('POST', 'waiter-flow/settle', [], ['facilityId' => $d['facilityId'], 'orderIds' => $ids, 'tenders' => [$tender]]);
        $receipt = $res->body['receiptId'] ?? null;

        return redirect()->route('waiter-flow.open-tabs', ['facilityId' => $d['facilityId']])->with('success', 'Payment recorded and the tab is closed.'.($receipt ? ' Receipt issued.' : ''));
    }

    public function reassign(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'facilityId' => ['required', 'uuid'], 'toStaffId' => ['required', 'uuid'], 'orderIds' => ['required', 'string'], 'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);
        $this->api->request('POST', 'waiter-flow/reassign', [], [
            'facilityId' => $d['facilityId'], 'toStaffId' => $d['toStaffId'], 'reason' => $d['reason'], 'orderIds' => array_values(array_filter(explode(',', $d['orderIds']))),
        ]);

        return redirect()->route('waiter-flow.open-tabs', ['facilityId' => $d['facilityId']])->with('success', 'Reassigned. The new waiter now sees these tabs on their tablet.');
    }

    public function handovers(Request $request, DashboardData $dash)
    {
        [$facilities, $facilityId] = $this->facilities($request, $dash);
        $status = (string) $request->query('status', '');
        $list = $facilityId === '' ? null : Fetch::of(fn () => $this->api->get('waiter-flow/handovers', ['facilityId' => $facilityId, 'direction' => 'all', 'status' => $status === '' ? 'PENDING,ACCEPTED,REJECTED,CANCELLED,EXPIRED,FORCED' : $status]), ['GET', '/waiter-flow/handovers']);

        return view('pages.operations.handovers', ['facilities' => $facilities, 'facilityId' => $facilityId, 'status' => $status, 'list' => $list, 'statuses' => self::HANDOVER_STATUSES]);
    }

    // ---- receipt print queue ------------------------------------------------------------------------------------------

    public function printQueue(Request $request, DashboardData $dash)
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());
        $facilityId = (string) $request->query('facilityId', '');
        if ($facilityId === '' || ! collect($flat)->contains('id', $facilityId)) {
            $facilityId = (string) ($flat[0]['id'] ?? '');
        }
        $status = (string) $request->query('status', 'QUEUED');
        $jobs = $facilityId === '' ? null : Fetch::of(fn () => $this->api->get('print-jobs', ['facilityId' => $facilityId, 'status' => $status]), ['GET', '/print-jobs']);

        return view('pages.operations.print-queue', ['facilities' => $flat, 'facilityId' => $facilityId, 'status' => $status, 'jobs' => $jobs]);
    }

    /** The receipt, laid out for the browser's print dialog; the page marks the job printed once printing is confirmed. */
    public function printJob(string $job)
    {
        $res = $this->api->request('GET', "print-jobs/{$job}");

        return view('pages.operations.print-job', ['job' => $res->body]);
    }

    public function printed(string $job): RedirectResponse
    {
        $this->api->request('POST', "print-jobs/{$job}/printed");

        return redirect()->route('print-queue')->with('success', 'Marked as printed.');
    }
}
