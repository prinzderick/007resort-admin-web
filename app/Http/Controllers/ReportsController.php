<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Support\Csv;
use App\Support\DataFreshness;
use App\Support\Fetch;
use App\Support\Money;
use App\Support\Time;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Reports: Property -> Facility -> Operating point -> Terminal -> Staff -> Transaction.
 * Every level that the API contract can answer is a real page; the rest are
 * shown as "pending API" in the drill-down trail rather than invented.
 */
class ReportsController extends Controller
{
    public function index(Request $request, DashboardData $dash)
    {
        $date = $this->date($request->query('date'));
        $facilities = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $rows = [];
        $blocks = [];

        foreach ($dash->flatten($facilities->items()) as $f) {
            $s = Fetch::of(fn () => $this->api->get('reports/facility-daily-summary', ['date' => $date, 'facilityId' => $f['id']]), ['GET', '/reports/facility-daily-summary']);
            if ($s->ok()) {
                $rows[] = ['facility' => $f, 's' => $s->data];
                $blocks[] = $s->data['freshness'] ?? null;
            }
        }

        $to = $this->date($request->query('to'), $date);
        $from = $this->date($request->query('from'), CarbonImmutable::parse($to)->subDays(6)->toDateString());
        $period = Fetch::of(fn () => $this->api->get('reports/revenue', ['from' => $from, 'to' => $to]), ['GET', '/reports/revenue']);
        $blocks[] = $period->ok() ? ($period->data['freshness'] ?? null) : null;

        if ($request->query('format') === 'csv') {
            return Csv::stream("property-{$date}.csv", ['Date', 'Facility', 'Orders', 'Gross sales', 'Discounts', 'Net sales', 'Refunds', 'Voids', 'Tickets redeemed'],
                array_map(fn ($r) => [$date, $r['facility']['name'] ?? '', $r['s']['orders'] ?? 0, $r['s']['grossSales'] ?? '0', $r['s']['discounts'] ?? '0', $r['s']['netSales'] ?? '0', $r['s']['refunds'] ?? '0', $r['s']['voids']['count'] ?? 0, $r['s']['ticketsRedeemed'] ?? 0], $rows));
        }

        return view('pages.reports.index', [
            'date' => $date, 'from' => $from, 'to' => $to, 'facilities' => $facilities, 'rows' => $rows, 'period' => $period,
            'total' => Money::sum(array_map(fn ($r) => $r['s']['netSales'] ?? '0', $rows)),
            'freshness' => DataFreshness::assess($blocks),
        ]);
    }

    public function facility(Request $request, string $facility)
    {
        $date = $this->date($request->query('date'));
        $summary = Fetch::of(fn () => $this->api->get('reports/facility-daily-summary', ['date' => $date, 'facilityId' => $facility]), ['GET', '/reports/facility-daily-summary']);
        $points = Fetch::of(fn () => $this->api->get("organization/facilities/{$facility}/operating-points"), ['GET', '/organization/facilities/{facilityId}/operating-points']);
        $sessions = Fetch::of(fn () => $this->api->get('cash-sessions', ['filter[facilityId]' => $facility, 'limit' => 50]), ['GET', '/cash-sessions']);
        $devices = Fetch::of(fn () => $this->api->get('devices', ['limit' => 200]), ['GET', '/devices']);
        $fac = Fetch::of(fn () => $this->api->get("organization/facilities/{$facility}"), ['GET', '/organization/facilities/{facilityId}']);

        $deviceNames = [];
        foreach ($devices->items() as $d) {
            $deviceNames[$d['id'] ?? ''] = $d['name'] ?? '';
        }
        $staffNames = [];

        if ($request->query('format') === 'csv' && $summary->ok()) {
            $s = $summary->data;
            $rows = [];
            foreach ($s['byTender'] ?? [] as $t) {
                $rows[] = ['payments by method', $t['tenderType'] ?? '', $t['count'] ?? 0, $t['amount'] ?? '0'];
            }
            foreach ($s['topProducts'] ?? [] as $p) {
                $rows[] = ['top product', $p['name'] ?? '', $p['quantity'] ?? 0, $p['revenue'] ?? '0'];
            }

            return Csv::stream("facility-{$facility}-{$date}.csv", ['Section', 'Item', 'Count', 'Amount'], $rows);
        }

        return view('pages.reports.facility', [
            'date' => $date, 'facilityId' => $facility, 'facility' => $fac->data, 'summary' => $summary, 'points' => $points, 'sessions' => $sessions,
            'deviceNames' => $deviceNames, 'staffNames' => $staffNames,
            'freshness' => DataFreshness::assess($summary->ok() ? [$summary->data['freshness'] ?? null] : []),
        ]);
    }

    public function shift(Request $request, string $session)
    {
        $report = Fetch::of(fn () => $this->api->get("reports/cashier-shift/{$session}"), ['GET', '/reports/cashier-shift/{shiftId}']);
        // GET /payments is scoped by facility (or your own takings): pass the shift's facility so any
        // permitted viewer gets the whole shift, whichever way the API scopes an unfiltered list.
        $facilityId = $report->ok() ? ($report->data['facilityId'] ?? null) : null;
        $payments = Fetch::of(fn () => $this->api->get('payments', ['filter[cashSessionId]' => $session, 'filter[facilityId]' => $facilityId, 'limit' => 200]), ['GET', '/payments']);

        if ($request->query('format') === 'csv') {
            return Csv::stream("shift-{$session}.csv", ['Payment', 'Time', 'Method', 'Status', 'Amount', 'Refunded', 'Reference'],
                array_map(fn ($p) => [$p['id'] ?? '', $p['createdAt'] ?? '', $p['tenderType'] ?? '', $p['status'] ?? '', $p['amount'] ?? '0', $p['refundedAmount'] ?? '0', $p['providerReference'] ?? ''], $payments->items()));
        }

        return view('pages.reports.shift', [
            'session' => $session, 'report' => $report, 'payments' => $payments,
            'freshness' => DataFreshness::assess($report->ok() ? [$report->data['freshness'] ?? null] : []),
        ]);
    }

    private function date(?string $value, ?string $default = null): string
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : ($default ?? Time::today());
    }
}
