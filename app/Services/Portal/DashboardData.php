<?php

namespace App\Services\Portal;

use App\Auth\StaffSession;
use App\Services\R007Api\R007ApiClient;
use App\Support\DataFreshness;
use App\Support\Fetch;
use App\Support\Money;
use App\Support\Time;

/**
 * Assembles the owner dashboard from API reads. Every block degrades on its
 * own (Fetch) and the whole page carries one honest freshness verdict.
 */
class DashboardData
{
    private const MAX_FACILITIES = 12;

    public function __construct(private readonly R007ApiClient $api, private readonly StaffSession $staff) {}

    /** @return array<string, mixed> */
    public function build(?string $date = null): array
    {
        $date ??= Time::today();
        $out = ['date' => $date];

        // Site status: full detail for IT (config.manage), coarse health for everyone else.
        $out['sync'] = $this->staff->can('config.manage')
            ? Fetch::of(fn () => $this->api->get('sync/status'), ['GET', '/sync/status'])
            : new Fetch(null, 'forbidden');
        $out['health'] = Fetch::of(fn () => $this->api->get('system/health'), ['GET', '/system/health']);

        // Revenue, payments by method, orders, tickets: one daily summary per facility.
        $out['facilities'] = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $summaries = [];
        $freshBlocks = [];
        if ($this->staff->canAny('report.view', 'report.view.all')) {
            foreach (array_slice($this->flatten($out['facilities']->items()), 0, self::MAX_FACILITIES) as $f) {
                if (empty($f['id'])) {
                    continue;
                }
                $s = Fetch::of(fn () => $this->api->get('reports/facility-daily-summary', ['date' => $date, 'facilityId' => $f['id']]), ['GET', '/reports/facility-daily-summary']);
                if ($s->ok()) {
                    $summaries[] = ['facility' => $f, 'summary' => $s->data];
                    $freshBlocks[] = $s->data['freshness'] ?? null;
                }
            }
            $out['reportsFetch'] = $out['facilities']->ok() ? new Fetch(null) : $out['facilities'];
        } else {
            $out['reportsFetch'] = new Fetch(null, 'forbidden');
        }
        $out['revenue'] = array_map(fn ($r) => [
            'facility' => $r['facility']['name'] ?? '?', 'facilityId' => $r['facility']['id'] ?? '',
            'gross' => $r['summary']['grossSales'] ?? '0', 'net' => $r['summary']['netSales'] ?? '0', 'orders' => (int) ($r['summary']['orders'] ?? 0),
        ], $summaries);
        $out['totals'] = [
            'net' => Money::sum(array_column($out['revenue'], 'net')),
            'gross' => Money::sum(array_column($out['revenue'], 'gross')),
            'refunds' => Money::sum(array_map(fn ($r) => $r['summary']['refunds'] ?? '0', $summaries)),
            'orders' => array_sum(array_column($out['revenue'], 'orders')),
            'voids' => array_sum(array_map(fn ($r) => (int) ($r['summary']['voids']['count'] ?? 0), $summaries)),
            'ticketsRedeemed' => array_sum(array_map(fn ($r) => (int) ($r['summary']['ticketsRedeemed'] ?? 0), $summaries)),
        ];
        $methods = [];
        foreach ($summaries as $r) {
            foreach ($r['summary']['byTender'] ?? [] as $t) {
                $m = $t['tenderType'] ?? 'UNKNOWN';
                $methods[$m] ??= ['amount' => '0', 'count' => 0];
                $methods[$m]['amount'] = Money::add($methods[$m]['amount'], $t['amount'] ?? '0');
                $methods[$m]['count'] += (int) ($t['count'] ?? 0);
            }
        }
        $out['byMethod'] = $methods;

        // Orders
        $out['orders'] = $this->staff->can('order.view')
            ? Fetch::of(fn () => $this->api->get('orders', ['limit' => 100]), ['GET', '/orders'])
            : new Fetch(null, 'forbidden');
        $out['orderCounts'] = $this->countBy($out['orders']->items(), 'status');

        // Bookings
        $out['bookings'] = $this->staff->can('booking.view')
            ? Fetch::of(fn () => $this->api->get('bookings', ['limit' => 100]), ['GET', '/bookings'])
            : new Fetch(null, 'forbidden');
        $out['bookingCounts'] = $this->countBy($out['bookings']->items(), 'status');

        // Stock indicators: total on hand across locations vs reorder level
        $out['stock'] = new Fetch(null, 'forbidden');
        $out['lowStock'] = [];
        if ($this->staff->can('inventory.view')) {
            $items = $this->pages('inventory/items', ['GET', '/inventory/items']);
            $bal = $this->pages('inventory/balances', ['GET', '/inventory/balances']);
            $out['stock'] = $items->ok() ? $bal : $items;
            if ($items->ok() && $bal->ok()) {
                $onHand = [];
                foreach ($bal->items() as $b) {
                    $onHand[$b['itemId'] ?? ''] = bcadd($onHand[$b['itemId'] ?? ''] ?? '0', Money::norm($b['quantity'] ?? '0'), 4);
                }
                foreach ($items->items() as $it) {
                    $q = $onHand[$it['id'] ?? ''] ?? '0';
                    if (isset($it['reorderLevel']) && bccomp($q, Money::norm($it['reorderLevel']), 4) <= 0) {
                        $out['lowStock'][] = ['name' => $it['name'] ?? '', 'unit' => $it['unit'] ?? '', 'onHand' => $q, 'reorderLevel' => $it['reorderLevel']];
                    }
                }
            }
        }

        // Attendance today
        $out['attendance'] = $this->staff->can('attendance.view')
            ? Fetch::of(fn () => $this->api->get('attendance', ['filter[from]' => $date, 'filter[to]' => $date, 'limit' => 200]), ['GET', '/attendance'])
            : new Fetch(null, 'forbidden');
        $out['attendanceCounts'] = $this->countBy($out['attendance']->items(), 'status');

        // Approvals waiting on me
        $out['approvals'] = $this->staff->canApproveAnything()
            ? Fetch::of(fn () => $this->api->get('approvals', ['filter[status]' => 'PENDING', 'limit' => 100]), ['GET', '/approvals'])
            : new Fetch(null, 'forbidden');

        $out['freshness'] = DataFreshness::assess($freshBlocks, $out['sync']->ok() ? (array) $out['sync']->data : null);
        $out['siteStatus'] = $this->siteStatus($out);

        return $out;
    }

    /** All pages (up to 1000 rows) of a cursor list, as one Fetch. */
    private function pages(string $path, array $endpoint): Fetch
    {
        return Fetch::of(function () use ($path) {
            $items = [];
            $cursor = null;
            for ($i = 0; $i < 5; $i++) {
                $body = $this->api->get($path, ['limit' => 200, 'cursor' => $cursor]);
                array_push($items, ...array_values((array) ($body['items'] ?? [])));
                $cursor = $body['nextCursor'] ?? null;
                if (! is_string($cursor) || $cursor === '') {
                    break;
                }
            }

            return ['items' => $items, 'nextCursor' => null];
        }, $endpoint);
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
                'lastSyncAt' => $out['freshness']->lastSyncAt, 'detail' => $s];
        }

        $h = $out['health']->ok() ? ($out['health']->data['status'] ?? null) : null;

        return ['health' => match ($h) {
            'ok' => 'ONLINE', 'degraded' => 'DEGRADED', 'down' => 'OFFLINE', default => 'UNKNOWN'
        },
            'lastHeartbeatAt' => null, 'lastSyncAt' => $out['freshness']->lastSyncAt, 'detail' => null];
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
            array_push($flat, ...$this->flatten($children));
        }

        return $flat;
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
