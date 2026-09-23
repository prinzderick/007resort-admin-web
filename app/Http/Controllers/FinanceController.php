<?php

namespace App\Http\Controllers;

use App\Support\Csv;
use App\Support\Fetch;
use App\Support\Money;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function payments(Request $request)
    {
        $q = $request->only(['filter']);
        $filter = array_filter((array) ($q['filter'] ?? []), fn ($v) => is_string($v) && $v !== '');
        $params = ['limit' => 50, 'cursor' => $request->query('cursor')];
        foreach (['status', 'facilityId', 'from', 'to'] as $k) {
            isset($filter[$k]) && $params["filter[{$k}]"] = $filter[$k];
        }

        $payments = Fetch::of(fn () => $this->api->get('payments', $params), ['GET', '/payments']);
        $facilities = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);

        if ($request->query('format') === 'csv') {
            return Csv::stream('payments-'.Time::today().'.csv', ['Payment', 'Created', 'Facility', 'Method', 'Provider', 'Reference', 'Status', 'Amount', 'Refunded'],
                array_map(fn ($p) => [$p['id'], $p['createdAt'], $p['facilityId'], $p['tenderType'], $p['provider'] ?? '', $p['providerReference'] ?? '', $p['status'], $p['amount'], $p['refundedAmount'] ?? '0'], $payments->items()));
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

    /** Settlement reconciliation view (provider vs. captured), built from the payments the API exposes. */
    public function reconciliation(Request $request)
    {
        $from = $this->day($request->query('from')) ?? Time::today();
        $to = $this->day($request->query('to')) ?? Time::today();
        $payments = Fetch::of(fn () => $this->api->get('payments', ['filter[from]' => $from, 'filter[to]' => $to, 'limit' => 200]), ['GET', '/payments']);

        $byMethod = [];
        $provider = [];
        $unsettled = [];
        foreach ($payments->items() as $p) {
            $m = $p['tenderType'];
            $byMethod[$m] ??= ['count' => 0, 'captured' => '0', 'refunded' => '0'];
            $byMethod[$m]['count']++;
            if (in_array($p['status'], ['CAPTURED', 'PARTIALLY_REFUNDED', 'REFUNDED'], true)) {
                $byMethod[$m]['captured'] = Money::add($byMethod[$m]['captured'], $p['amount']);
            }
            $byMethod[$m]['refunded'] = Money::add($byMethod[$m]['refunded'], $p['refundedAmount'] ?? '0');

            if (($p['provider'] ?? 'MANUAL') !== 'MANUAL' && ! empty($p['providerReference'])) {
                $provider[] = $p;
                if (! in_array($p['status'], ['CAPTURED', 'PARTIALLY_REFUNDED', 'REFUNDED', 'REVERSED'], true)) {
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

    private function day(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
