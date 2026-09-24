<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Services\Portal\Directory;
use App\Support\Csv;
use App\Support\DateRange;
use App\Support\Fetch;
use App\Support\Money;
use App\Support\Time;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function payments(Request $request)
    {
        $q = $request->only(['filter']);
        $filter = array_filter((array) ($q['filter'] ?? []), fn ($v) => is_string($v) && $v !== '');
        $params = ['limit' => 50, 'cursor' => $request->query('cursor')];
        foreach (['status', 'facilityId', 'tenderType'] as $k) {
            isset($filter[$k]) && $params["filter[{$k}]"] = $filter[$k];
        }
        // The picker is in property (Lagos) days; the API filters on UTC instants.
        isset($filter['from']) && $params['filter[from]'] = $this->dayStart($filter['from']);
        isset($filter['to']) && $params['filter[to]'] = $this->dayEnd($filter['to']);

        $payments = Fetch::of(fn () => $this->api->get('payments', $params), ['GET', '/payments']);
        $facilities = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);

        if ($request->query('format') === 'csv') {
            return Csv::stream('payments-'.Time::today().'.csv', ['Payment', 'Created', 'Facility', 'Method', 'Provider', 'Reference', 'Status', 'Amount', 'Refunded'],
                array_map(fn ($p) => [$p['id'] ?? '', $p['createdAt'] ?? '', $p['facilityId'] ?? '', $p['tenderType'] ?? '', $p['provider'] ?? '', $p['providerReference'] ?? $p['reference'] ?? '', $p['status'] ?? '', $p['amount'] ?? '0', $p['refundedAmount'] ?? '0'], $payments->items()));
        }

        return view('pages.finance.payments', ['payments' => $payments, 'filter' => $filter, 'facilities' => $facilities]);
    }

    public function payment(string $payment)
    {
        $p = Fetch::of(fn () => $this->api->get("payments/{$payment}"), ['GET', '/payments/{paymentId}']);

        return view('pages.finance.payment', ['payment' => $p, 'id' => $payment]);
    }

    public function refund(Request $request, string $payment): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        abort_if(Money::cmp($data['amount'], '0') <= 0, 422, 'Refund amount must be greater than zero.');

        $res = $this->api->request('POST', "payments/{$payment}/refund", [], $data, $this->stepUp($request));

        return $this->done($res, 'finance.payment', 'Refund completed.', 'The refund was accepted but must be approved before any money moves.', ['payment' => $payment]);
    }

    public function reversal(Request $request, string $payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $res = $this->api->request('POST', "payments/{$payment}/reversal", [], $data, $this->stepUp($request));

        return $this->done($res, 'finance.payment', 'Payment reversed.', 'The reversal was accepted but must be approved before it takes effect.', ['payment' => $payment]);
    }

    /** Refunded, part-refunded and reversed payments, plus refund/reversal requests still waiting for approval. */
    public function refunds(Request $request, DashboardData $dash, Directory $dir)
    {
        $range = DateRange::fromRequest($request, '30d');
        $from = $this->dayStart($range->from);
        $to = $this->dayEnd($range->to);
        $rows = [];
        $fetch = new Fetch(['items' => []]);
        foreach (['PARTIALLY_REFUNDED', 'REFUNDED', 'REVERSED'] as $st) {
            $r = Fetch::of(fn () => $this->api->get('payments', ['limit' => 100, 'filter[status]' => $st, 'filter[from]' => $from, 'filter[to]' => $to]), ['GET', '/payments']);
            if (! $r->ok()) {
                $fetch = $r;
                break;
            }
            array_push($rows, ...$r->items());
        }
        usort($rows, fn ($a, $b) => strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? '')));
        $pending = $this->staff->canApproveAnything() || $this->staff->can('refund.execute')
            ? Fetch::of(fn () => $this->api->get('approvals', ['filter[status]' => 'PENDING', 'limit' => 100]), ['GET', '/approvals'])
            : new Fetch(null, 'forbidden');
        $waiting = array_values(array_filter($pending->items(), fn ($a) => in_array($a['action'] ?? '', ['payment.refund', 'payment.reversal'], true)));
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $names = [];
        foreach ($dash->flatten($tree->items()) as $f) {
            $names[$f['id'] ?? ''] = $f['name'] ?? '';
        }

        if ($request->query('format') === 'csv') {
            return Csv::stream('refunds-and-reversals.csv', ['Payment', 'Created', 'Facility', 'Method', 'Status', 'Amount', 'Refunded'],
                array_map(fn ($p) => [$p['id'] ?? '', $p['createdAt'] ?? '', $names[$p['facilityId'] ?? ''] ?? '', $p['tenderType'] ?? '', $p['status'] ?? '', $p['amount'] ?? '0', $p['refundedAmount'] ?? '0'], $rows));
        }

        return view('pages.finance.refunds', ['fetch' => $fetch, 'rows' => $rows, 'waiting' => $waiting, 'pendingFetch' => $pending, 'facilityNames' => $names, 'range' => $range]);
    }

    /** Cash sessions (shifts) across the property, with expected vs counted cash. */
    public function cashSessions(Request $request, DashboardData $dash, Directory $dir)
    {
        $range = DateRange::fromRequest($request, '7d');
        $status = $request->query('status');
        $facilityId = $request->query('facility');
        $sessions = Fetch::of(fn () => $this->api->get('cash-sessions', ['limit' => 100, 'cursor' => $request->query('cursor'), 'filter[status]' => $status, 'filter[facilityId]' => $facilityId]), ['GET', '/cash-sessions']);
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $names = [];
        foreach ($dash->flatten($tree->items()) as $f) {
            $names[$f['id'] ?? ''] = $f['name'] ?? '';
        }
        $items = array_values(array_filter($sessions->items(), fn ($c) => ($c['openedAt'] ?? '') >= $this->dayStart($range->from) && ($c['openedAt'] ?? '') < $this->dayEnd($range->to)));

        if ($request->query('format') === 'csv') {
            $staffNames = $dir->staffNames();

            return Csv::stream('cash-sessions.csv', ['Opened', 'Closed', 'Facility', 'Cashier', 'Status', 'Opening float', 'Expected cash', 'Counted', 'Variance'],
                array_map(fn ($c) => [$c['openedAt'] ?? '', $c['closedAt'] ?? '', $names[$c['facilityId'] ?? ''] ?? '', $staffNames[$c['staffId'] ?? ''] ?? ($c['staffId'] ?? ''), $c['status'] ?? '', $c['openingFloat'] ?? '', $c['expectedCash'] ?? '', $c['countedCash'] ?? '', $c['variance'] ?? ''], $items));
        }

        return view('pages.finance.cash-sessions', ['sessions' => $sessions, 'items' => $items, 'facilities' => $dash->flatten($tree->items()), 'facilityNames' => $names, 'staffNames' => $dir->staffNames(), 'status' => $status, 'facilityId' => $facilityId, 'range' => $range]);
    }

    /** Settlement reconciliation view (provider vs. captured), built from the payments the API exposes. */
    public function reconciliation(Request $request)
    {
        $range = DateRange::fromRequest($request, '7d');
        $from = $range->from;
        $to = $range->to;
        $payments = $this->all('payments', ['filter[from]' => $this->dayStart($from), 'filter[to]' => $this->dayEnd($to)], ['GET', '/payments'], 10);

        $byMethod = [];
        $provider = [];
        $unsettled = [];
        foreach ($payments->items() as $p) {
            $m = $p['tenderType'] ?? 'UNKNOWN';
            $byMethod[$m] ??= ['count' => 0, 'captured' => '0', 'refunded' => '0'];
            $byMethod[$m]['count']++;
            if (in_array($p['status'] ?? '', ['CAPTURED', 'PARTIALLY_REFUNDED', 'REFUNDED'], true)) {
                $byMethod[$m]['captured'] = Money::add($byMethod[$m]['captured'], $p['amount'] ?? '0');
            }
            $byMethod[$m]['refunded'] = Money::add($byMethod[$m]['refunded'], $p['refundedAmount'] ?? '0');

            if (($p['provider'] ?? 'MANUAL') !== 'MANUAL' && ! empty($p['providerReference'])) {
                $provider[] = $p;
                if (! in_array($p['status'] ?? '', ['CAPTURED', 'PARTIALLY_REFUNDED', 'REFUNDED', 'REVERSED'], true)) {
                    $unsettled[] = $p;
                }
            }
        }

        return view('pages.finance.reconciliation', compact('payments', 'from', 'to', 'byMethod', 'provider', 'unsettled'));
    }

    public function verifyPaystack(Request $request): RedirectResponse
    {
        $ref = $request->validate(['reference' => ['required', 'regex:/^[A-Za-z0-9_\-]+$/', 'max:100']])['reference'];
        $r = $this->api->get('payments/paystack/verify/'.rawurlencode($ref));

        return redirect()->back()->with('success', "Paystack reports {$ref} as ".($r['status'] ?? 'unknown').(isset($r['amount']) ? ' ('.Money::format($r['amount']).')' : '').'.');
    }

    /** @return array<string, string> */
    private function stepUp(Request $request): array
    {
        $t = trim((string) $request->input('stepUpToken', ''));

        return $t !== '' ? ['X-Step-Up-Token' => $t] : [];
    }

    /** Start of a Lagos calendar day as a UTC instant. */
    private function dayStart(mixed $day): ?string
    {
        return $this->day($day) ? CarbonImmutable::parse($day, (string) config('r007.display_timezone', 'Africa/Lagos'))->startOfDay()->utc()->toIso8601ZuluString('millisecond') : null;
    }

    /** End (exclusive) of a Lagos calendar day as a UTC instant. */
    private function dayEnd(mixed $day): ?string
    {
        return $this->day($day) ? CarbonImmutable::parse($day, (string) config('r007.display_timezone', 'Africa/Lagos'))->addDay()->startOfDay()->utc()->toIso8601ZuluString('millisecond') : null;
    }

    private function day(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
