<?php

namespace App\Services\Portal;

use App\Auth\StaffSession;
use App\Services\R007Api\R007ApiClient;
use App\Support\DataFreshness;
use App\Support\DateRange;
use App\Support\Fetch;
use App\Support\Money;
use App\Support\Time;
use Carbon\CarbonImmutable;

/**
 * Assembles the owner dashboard from API reads for a date range (and the equally long period before it, for "% vs previous").
 * Every block degrades on its own (Fetch) and the whole page carries one honest freshness verdict.
 *
 * What the API gives us and how it is used:
 *  - GET /reports/revenue?from&to   -> sales, orders, payments by method (captured/refunded), sales by facility. One call per
 *    day for the history line, one for each whole period. Needs report.view.all; without it we fall back to per-facility calls.
 *  - GET /payments?filter[from]&filter[to] -> recent transactions, success rate by method, and the by-time heatmap. The API
 *    returns at most 200 rows per page, so on a very busy range these three are computed from the latest 200 payments (said on screen).
 *  - GET /reports/facility-daily-summary -> voids and tickets redeemed for the last day of the range.
 */
class DashboardData
{
    private const MAX_FACILITIES = 12;

    private const MAX_HISTORY_DAYS = 31;

    /** @var array<string, array{fetch: Fetch, data: array<string, mixed>}> revenue reads of this request */
    private array $memo = [];

    public function __construct(private readonly R007ApiClient $api, private readonly StaffSession $staff) {}

    /** @return array<string, mixed> */
    public function build(?DateRange $range = null): array
    {
        $range ??= DateRange::preset('7d');
        $prev = $range->previous();
        $out = ['range' => $range, 'previous' => $prev, 'date' => $range->to];

        // Site status: full detail for IT (config.manage), coarse health for everyone else.
        $out['sync'] = $this->staff->can('config.manage')
            ? Fetch::of(fn () => $this->api->get('sync/status'), ['GET', '/sync/status'])
            : new Fetch(null, 'forbidden');
        $out['health'] = Fetch::of(fn () => $this->api->get('system/health'), ['GET', '/system/health']);
        $out['facilities'] = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $this->flatten($out['facilities']->items());
        $freshBlocks = [];

        $canReport = $this->staff->canAny('report.view', 'report.view.all');
        $out['reportsFetch'] = $canReport ? new Fetch(null) : new Fetch(null, 'forbidden');

        // ---- revenue: this period, the previous period, and the day-by-day history --------------------------------
        $cur = $prv = null;
        $history = ['labels' => [], 'current' => [], 'previous' => []];
        if ($canReport) {
            $cur = $this->revenue($range->from, $range->to, $flat);
            $prv = $this->revenue($prev->from, $prev->to, $flat);
            $out['reportsFetch'] = $cur['fetch'];
            $cur['fetch']->ok() && $freshBlocks[] = $cur['data']['freshness'] ?? null;

            if ($cur['fetch']->ok() && $range->days() <= self::MAX_HISTORY_DAYS) {
                $prevDays = $prev->each();
                foreach ($range->each() as $i => $day) {
                    $d = $this->revenue($day, $day, $flat)['data'];
                    $p = isset($prevDays[$i]) ? $this->revenue($prevDays[$i], $prevDays[$i], $flat)['data'] : [];
                    $history['labels'][] = CarbonImmutable::parse($day)->format($range->days() > 8 ? 'M j' : 'D j');
                    $history['current'][] = (float) ($d['revenue'] ?? 0);
                    $history['previous'][] = (float) ($p['revenue'] ?? 0);
                }
            }
        }
        $out['history'] = $history;

        $c = (array) ($cur['data'] ?? []);
        $p = (array) ($prv['data'] ?? []);
        $sum = fn (array $d, string $k) => Money::sum(array_map(fn ($m) => $m[$k] ?? '0', (array) ($d['byPaymentMethod'] ?? [])));
        $count = fn (array $d) => array_sum(array_map(fn ($m) => (int) ($m['count'] ?? 0), (array) ($d['byPaymentMethod'] ?? [])));
        $kpi = [
            'sales' => ['now' => (string) ($c['revenue'] ?? '0'), 'prev' => (string) ($p['revenue'] ?? '0')],
            'orders' => ['now' => (int) ($c['orders'] ?? 0), 'prev' => (int) ($p['orders'] ?? 0)],
            'payments' => ['now' => $count($c), 'prev' => $count($p)],
            'refunds' => ['now' => $sum($c, 'refunded'), 'prev' => $sum($p, 'refunded')],
            'avg' => ['now' => ($c['orders'] ?? 0) > 0 ? bcdiv((string) ($c['revenue'] ?? '0'), (string) $c['orders'], 4) : '0', 'prev' => ($p['orders'] ?? 0) > 0 ? bcdiv((string) ($p['revenue'] ?? '0'), (string) $p['orders'], 4) : '0'],
        ];
        foreach ($kpi as $k => $v) {
            $kpi[$k]['delta'] = $this->delta($v['now'], $v['prev']);
        }
        $out['kpi'] = $kpi;
        $out['hasRevenue'] = $canReport && $cur && $cur['fetch']->ok();
        $out['byMethod'] = array_map(fn ($m) => ['method' => $m['tenderType'] ?? 'UNKNOWN', 'count' => (int) ($m['count'] ?? 0), 'captured' => (string) ($m['captured'] ?? '0'), 'net' => (string) ($m['net'] ?? '0')], (array) ($c['byPaymentMethod'] ?? []));
        $totalRev = (string) ($c['revenue'] ?? '0');
        $out['byFacility'] = array_map(fn ($f) => [
            'id' => $f['facilityId'] ?? '', 'name' => $f['name'] ?? ($f['code'] ?? '?'), 'orders' => (int) ($f['orders'] ?? 0), 'revenue' => (string) ($f['revenue'] ?? '0'),
            'share' => Money::cmp($totalRev, '0') > 0 ? (float) bcmul(bcdiv((string) ($f['revenue'] ?? '0'), $totalRev, 6), '100', 1) : 0.0,
        ], (array) ($c['byFacility'] ?? []));

        // ---- payments: recent transactions, success rate, by-time heatmap -------------------------------------------
        $out['payments'] = new Fetch(null, 'forbidden');
        $out['recent'] = [];
        $out['success'] = null;
        $out['heatmap'] = null;
        if ($this->staff->can('payment.view')) {
            $pay = Fetch::of(fn () => $this->api->get('payments', ['limit' => 200, 'filter[from]' => $this->dayStart($range->from), 'filter[to]' => $this->dayEnd($range->to)]), ['GET', '/payments']);
            $out['payments'] = $pay;
            if ($pay->ok()) {
                $rows = $pay->items();
                $out['recent'] = array_slice($rows, 0, 6);
                $out['success'] = $this->successRate($rows);
                $out['heatmap'] = $this->heatmap($rows);
                $out['paymentsCapped'] = count($rows) >= 200;
            }
        }

        // ---- the last day of the range: voids, tickets, per facility -------------------------------------------------
        $summaries = [];
        if ($canReport) {
            foreach (array_slice(array_values(array_filter($flat, fn ($f) => ! empty($f['id']) && in_array('POS', (array) ($f['capabilities'] ?? []), true))), 0, self::MAX_FACILITIES) as $f) {
                $s = Fetch::of(fn () => $this->api->get('reports/facility-daily-summary', ['date' => $range->to, 'facilityId' => $f['id']]), ['GET', '/reports/facility-daily-summary']);
                if ($s->ok()) {
                    $summaries[] = ['facility' => $f, 'summary' => $s->data];
                    $freshBlocks[] = $s->data['freshness'] ?? null;
                }
            }
        }
        $out['day'] = [
            'voids' => array_sum(array_map(fn ($r) => (int) ($r['summary']['voids']['count'] ?? 0), $summaries)),
            'tickets' => array_sum(array_map(fn ($r) => (int) ($r['summary']['ticketsRedeemed'] ?? 0), $summaries)),
        ];

        // ---- operations snapshot ---------------------------------------------------------------------------------------
        $out['orders'] = $this->staff->can('order.view') ? Fetch::of(fn () => $this->api->get('orders', ['limit' => 100]), ['GET', '/orders']) : new Fetch(null, 'forbidden');
        $out['orderCounts'] = $this->countBy($out['orders']->items(), 'status');
        $out['bookings'] = $this->staff->can('booking.view') ? Fetch::of(fn () => $this->api->get('bookings', ['limit' => 100]), ['GET', '/bookings']) : new Fetch(null, 'forbidden');
        $out['bookingCounts'] = $this->countBy($out['bookings']->items(), 'status');
        $out['attendance'] = $this->staff->can('attendance.view')
            ? Fetch::of(fn () => $this->api->get('attendance', ['filter[from]' => $range->to, 'filter[to]' => $range->to, 'limit' => 200]), ['GET', '/attendance'])
            : new Fetch(null, 'forbidden');
        $out['attendanceCounts'] = $this->countBy($out['attendance']->items(), 'status');

        // ---- alerts ----------------------------------------------------------------------------------------------------
        $out['approvals'] = $this->staff->canApproveAnything()
            ? Fetch::of(fn () => $this->api->get('approvals', ['filter[status]' => 'PENDING', 'scope' => 'approvable', 'limit' => 100]), ['GET', '/approvals'])
            : new Fetch(null, 'forbidden');
        $out['stock'] = new Fetch(null, 'forbidden');
        $out['lowStock'] = [];
        if ($this->staff->can('inventory.view')) {
            $bal = Fetch::of(fn () => $this->api->get('inventory/balances', ['belowReorder' => 1, 'limit' => 200]), ['GET', '/inventory/balances']);
            $out['stock'] = $bal;
            foreach ($bal->items() as $b) {
                $out['lowStock'][] = ['name' => $b['itemName'] ?? '', 'unit' => $b['unit'] ?? '', 'onHand' => (string) ($b['quantity'] ?? '0'), 'reorderLevel' => $b['reorderLevel'] ?? ''];
            }
        }
        $out['alerts'] = $this->alerts($out);

        $out['freshness'] = DataFreshness::assess($freshBlocks, $out['sync']->ok() ? (array) $out['sync']->data : null);
        $out['siteStatus'] = $this->siteStatus($out);

        return $out;
    }

    /**
     * Revenue for a range: site-wide when permitted, otherwise summed from the facilities the account may report on.
     *
     * @param  list<array<string, mixed>>  $flat
     * @return array{fetch: Fetch, data: array<string, mixed>}
     */
    private function revenue(string $from, string $to, array $flat): array
    {
        $key = "revenue:{$from}:{$to}";
        $this->memo[$key] ??= (function () use ($from, $to, $flat): array {
            $f = Fetch::of(fn () => $this->api->get('reports/revenue', ['from' => $from, 'to' => $to]), ['GET', '/reports/revenue']);
            if ($f->ok()) {
                return ['fetch' => $f, 'data' => (array) $f->data];
            }
            if ($f->state !== 'forbidden') {
                return ['fetch' => $f, 'data' => []];
            }
            // No site-wide right: add up the facilities we may see.
            $agg = ['revenue' => '0', 'orders' => 0, 'byFacility' => [], 'byPaymentMethod' => [], 'freshness' => null];
            $any = false;
            foreach (array_slice($flat, 0, self::MAX_FACILITIES * 2) as $fac) {
                if (empty($fac['id'])) {
                    continue;
                }
                $r = Fetch::of(fn () => $this->api->get('reports/revenue', ['from' => $from, 'to' => $to, 'facilityId' => $fac['id']]), ['GET', '/reports/revenue']);
                if (! $r->ok()) {
                    continue;
                }
                $any = true;
                $d = (array) $r->data;
                $agg['revenue'] = Money::add($agg['revenue'], $d['revenue'] ?? '0');
                $agg['orders'] += (int) ($d['orders'] ?? 0);
                $agg['freshness'] ??= $d['freshness'] ?? null;
                foreach ((array) ($d['byFacility'] ?? []) as $row) {
                    $agg['byFacility'][$row['facilityId'] ?? ''] = $row;
                }
                foreach ((array) ($d['byPaymentMethod'] ?? []) as $m) {
                    $t = $m['tenderType'] ?? '?';
                    $agg['byPaymentMethod'][$t] ??= ['tenderType' => $t, 'count' => 0, 'captured' => '0', 'refunded' => '0', 'net' => '0'];
                    $agg['byPaymentMethod'][$t]['count'] += (int) ($m['count'] ?? 0);
                    foreach (['captured', 'refunded', 'net'] as $k) {
                        $agg['byPaymentMethod'][$t][$k] = Money::add($agg['byPaymentMethod'][$t][$k], $m[$k] ?? '0');
                    }
                }
            }
            $agg['byFacility'] = array_values($agg['byFacility']);
            $agg['byPaymentMethod'] = array_values($agg['byPaymentMethod']);

            return $any ? ['fetch' => new Fetch($agg), 'data' => $agg] : ['fetch' => $f, 'data' => []];
        })();

        return $this->memo[$key];
    }

    private function delta(mixed $now, mixed $prev): ?float
    {
        $n = is_string($now) ? (float) $now : (float) $now;
        $p = is_string($prev) ? (float) $prev : (float) $prev;
        if ($p == 0.0) {
            return $n == 0.0 ? 0.0 : null;
        }

        return round(($n - $p) / abs($p) * 100, 1);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{rate: float, ok: int, total: int, byMethod: list<array{method: string, rate: float, total: int}>, best: ?array<string, mixed>}|null
     */
    private function successRate(array $rows): ?array
    {
        $ok = ['CAPTURED', 'PARTIALLY_REFUNDED', 'REFUNDED', 'REVERSED'];
        $judged = array_values(array_filter($rows, fn ($p) => in_array($p['status'] ?? '', [...$ok, 'FAILED', 'CANCELLED'], true)));
        if ($judged === []) {
            return null;
        }
        $by = [];
        foreach ($judged as $p) {
            $m = $p['tenderType'] ?? 'UNKNOWN';
            $by[$m]['total'] = ($by[$m]['total'] ?? 0) + 1;
            $by[$m]['ok'] = ($by[$m]['ok'] ?? 0) + (in_array($p['status'], $ok, true) ? 1 : 0);
        }
        $methods = [];
        foreach ($by as $m => $v) {
            $methods[] = ['method' => $m, 'rate' => round($v['ok'] / $v['total'] * 100, 1), 'total' => $v['total']];
        }
        usort($methods, fn ($a, $b) => [$b['rate'], $b['total']] <=> [$a['rate'], $a['total']]);
        $good = count(array_filter($judged, fn ($p) => in_array($p['status'], $ok, true)));

        return ['rate' => round($good / count($judged) * 100, 1), 'ok' => $good, 'total' => count($judged), 'byMethod' => $methods, 'best' => $methods[0] ?? null];
    }

    /**
     * Payments by weekday (Mon..Sun) and 2-hour block in property time.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{cells: list<list<int>>, max: int, blocks: list<string>, days: list<string>}
     */
    private function heatmap(array $rows): array
    {
        $blocks = ['12-2am', '2-4am', '4-6am', '6-8am', '8-10am', '10am-12', '12-2pm', '2-4pm', '4-6pm', '6-8pm', '8-10pm', '10pm-12'];
        $cells = array_fill(0, 12, array_fill(0, 7, 0));
        $tz = (string) config('r007.display_timezone', 'Africa/Lagos');
        foreach ($rows as $p) {
            $t = Time::parse($p['createdAt'] ?? null);
            if (! $t) {
                continue;
            }
            $l = $t->setTimezone($tz);
            $cells[intdiv((int) $l->format('G'), 2)][(int) $l->format('N') - 1]++;
        }

        return ['cells' => $cells, 'max' => max(1, max(array_map('max', $cells))), 'blocks' => $blocks, 'days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']];
    }

    /**
     * What needs a person, most urgent first.
     *
     * @param  array<string, mixed>  $out
     * @return list<array{tone: string, text: string, route: string}>
     */
    private function alerts(array $out): array
    {
        $a = [];
        if ($out['approvals']->ok() && ($n = count($out['approvals']->items())) > 0) {
            $a[] = ['tone' => 'warn', 'text' => $n === 1 ? '1 request is waiting for your approval' : "{$n} requests are waiting for your approval", 'route' => 'approvals'];
        }
        if ($out['sync']->ok()) {
            $s = (array) $out['sync']->data;
            $failed = (int) ($s['outbox']['failed'] ?? 0) + (int) ($s['inbox']['failed'] ?? 0);
            $failed > 0 && $a[] = ['tone' => 'bad', 'text' => "{$failed} sync event(s) failed", 'route' => 'sync'];
            ($c = (int) ($s['openConflicts'] ?? 0)) > 0 && $a[] = ['tone' => 'bad', 'text' => "{$c} sync conflict(s) need a decision", 'route' => 'sync'];
            ($s['health'] ?? null) !== 'ONLINE' && ! empty($s['lastError']) && $a[] = ['tone' => 'warn', 'text' => 'The two nodes are not syncing: '.(is_scalar($s['lastError']) ? $s['lastError'] : 'see Sync & IT'), 'route' => 'sync'];
        }
        $low = count($out['lowStock']);
        $low > 0 && $a[] = ['tone' => 'warn', 'text' => "{$low} stock line(s) at or below reorder level", 'route' => 'inventory.index'];
        ($r = (int) ($out['attendanceCounts']['NEEDS_REVIEW'] ?? 0)) > 0 && $a[] = ['tone' => 'warn', 'text' => "{$r} attendance record(s) need review", 'route' => 'staff.attendance'];

        return $a;
    }

    /**
     * @param  array<string, mixed>  $out
     * @return array{health: string, lastHeartbeatAt: ?string, lastSyncAt: ?string, detail: ?array<string, mixed>}
     */
    private function siteStatus(array $out): array
    {
        if ($out['sync']->ok()) {
            $s = (array) $out['sync']->data;

            return ['health' => (string) ($s['health'] ?? 'UNKNOWN'), 'lastHeartbeatAt' => $s['lastPeerHeartbeatAt'] ?? $s['lastHeartbeatAt'] ?? null,
                'lastSyncAt' => $out['freshness']->lastSyncAt ?? ($s['lastPushAt'] ?? null), 'detail' => $s];
        }

        $h = $out['health']->ok() ? ($out['health']->data['status'] ?? null) : null;

        return ['health' => match ($h) {
            'ok' => 'ONLINE', 'degraded' => 'DEGRADED', 'down' => 'OFFLINE', default => 'UNKNOWN'
        },
            'lastHeartbeatAt' => null, 'lastSyncAt' => null, 'detail' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    public function flatten(array $nodes): array
    {
        $flat = [];
        foreach ($nodes as $n) {
            $children = $n['children'] ?? [];
            unset($n['children']);
            $flat[] = $n;
            array_push($flat, ...$this->flatten((array) $children));
        }

        return $flat;
    }

    private function dayStart(string $day): string
    {
        return CarbonImmutable::parse($day, (string) config('r007.display_timezone', 'Africa/Lagos'))->startOfDay()->utc()->toIso8601ZuluString('millisecond');
    }

    private function dayEnd(string $day): string
    {
        return CarbonImmutable::parse($day, (string) config('r007.display_timezone', 'Africa/Lagos'))->addDay()->startOfDay()->utc()->toIso8601ZuluString('millisecond');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function countBy(array $rows, string $key): array
    {
        $c = [];
        foreach ($rows as $r) {
            $k = (string) ($r[$key] ?? 'UNKNOWN');
            $c[$k] = ($c[$k] ?? 0) + 1;
        }
        ksort($c);

        return $c;
    }
}
